<?php
header('Content-Type: application/json');
require '../config/db.php';
require '../config/auth.php';

// Server-side Permission Check
if (!has_permission('access_chat')) {
    echo json_encode(["status" => "error", "message" => "Unauthorized: You do not have permission to use the Chat Agent."]);
    exit;
}

// Enable error reporting for severe issues but prevent HTML output in JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // 0. Ensure column_aliases table exists (auto-create on first run)
    $checkAliasTable = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'column_aliases'";
    $aliasTableExists = $conn->query($checkAliasTable)->fetch_row();

    if (!$aliasTableExists) {
        // Drop old table if exists (to fix key length issues)
        @$conn->query("DROP TABLE IF EXISTS column_aliases");

        // Create the table
        $createAliasTable = "
        CREATE TABLE column_aliases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            table_name VARCHAR(50) NOT NULL,
            actual_column_name VARCHAR(50) NOT NULL,
            alias VARCHAR(50) NOT NULL,
            description VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_alias (table_name(20), actual_column_name(20), alias(20)),
            INDEX idx_table (table_name),
            INDEX idx_actual (actual_column_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $conn->query($createAliasTable);

        // Insert default aliases
        $defaultAliases = [
            ['employees', 'mobile_no', 'phone_no', 'Employee phone number'],
            ['employees', 'mobile_no', 'phone', 'Employee phone'],
            ['employees', 'mobile_no', 'contact_no', 'Employee contact'],
            ['employees', 'employee_id', 'emp_id', 'Employee ID'],
            ['candidates', 'email_id', 'email', 'Email address'],
            ['candidates', 'total_experience', 'experience', 'Years of experience'],
            ['candidates', 'jd_id', 'job_id', 'Job ID'],
            ['candidates', 'rating', 'score', 'Rating/score'],
            ['Job_List', 'task_no', 'task_number', 'Task number'],
            ['Job_List', 'jd_id', 'job_id', 'Job ID'],
        ];

        $stmt = $conn->prepare("INSERT INTO column_aliases (table_name, actual_column_name, alias, description) VALUES (?, ?, ?, ?)");
        foreach ($defaultAliases as $alias) {
            $stmt->bind_param("ssss", $alias[0], $alias[1], $alias[2], $alias[3]);
            @$stmt->execute();
        }
        $stmt->close();
    }

    // 1. Get the API Keys from Config
    $keyQuery = $conn->query("SELECT openai_api_key, serper_api_key FROM config WHERE id = 1 LIMIT 1");
    if (!$keyQuery || $keyQuery->num_rows === 0) {
        throw new Exception("Config table missing or uninitialized.");
    }
    $configRow = $keyQuery->fetch_assoc();
    $openaiApiKey = $configRow['openai_api_key'];
    $serperApiKey = $configRow['serper_api_key'];

    if (empty($openaiApiKey)) {
        throw new Exception("OpenAI API key is not configured.");
    }

    // 2. Read Request
    $data = json_decode(file_get_contents('php://input'), true);
    $userMessage = $data['message'] ?? '';
    $currentUser = $data['user'] ?? [];
    $callerId = $currentUser['id'] ?? '';
    $callerRole = $currentUser['role'] ?? '';

    if (empty($userMessage)) {
        throw new Exception("Message cannot be empty.");
    }

    // 2.2 Chat Ticketing & History Management
    $ticketId = null;
    $ticketNo = null;

    // Check for an open ticket for this user
    $tkt_stmt = $conn->prepare("SELECT id, ticket_no FROM chat_tickets WHERE employee_id = ? AND status = 'Open' LIMIT 1");
    $tkt_stmt->bind_param("s", $callerId);
    $tkt_stmt->execute();
    $tkt_stmt->bind_result($existingId, $existingNo);
    if ($tkt_stmt->fetch()) {
        $ticketId = $existingId;
        $ticketNo = $existingNo;
    }
    $tkt_stmt->close();

    // Create a new ticket if none exists
    if (!$ticketId) {
        $ticketNo = "TKT-" . strtoupper(substr(md5(time() . $callerId), 0, 8));
        $new_tkt_stmt = $conn->prepare("INSERT INTO chat_tickets (ticket_no, employee_id, status) VALUES (?, ?, 'Open')");
        $new_tkt_stmt->bind_param("ss", $ticketNo, $callerId);
        $new_tkt_stmt->execute();
        $ticketId = $new_tkt_stmt->insert_id;
        $new_tkt_stmt->close();
    }

    // Save user message to history
    $save_user_stmt = $conn->prepare("INSERT INTO chat_history (ticket_id, sender, message) VALUES (?, 'User', ?)");
    $save_user_stmt->bind_param("is", $ticketId, $userMessage);
    $save_user_stmt->execute();
    $save_user_stmt->close();

    // Fetch last 10 messages for context
    $history_res = $conn->query("SELECT sender, message FROM chat_history WHERE ticket_id = $ticketId ORDER BY created_at ASC LIMIT 10");
    $chatHistoryContext = [];
    while ($h_row = $history_res->fetch_assoc()) {
        $role = ($h_row['sender'] === 'User') ? 'user' : 'assistant';
        $chatHistoryContext[] = ["role" => $role, "content" => $h_row['message']];
    }

    // 2.5 Fetch Live System Statistics for Instant Awareness
    $employeeCount = 0;
    $candidateCount = 0;
    $jobCount = 0;
    $candidatesToday = 0;
    $jobsToday = 0;

    $res = $conn->query("SELECT COUNT(*) as total FROM employees");
    if ($res) $employeeCount = $res->fetch_assoc()['total'];

    $res = $conn->query("SELECT COUNT(*) as total FROM candidates");
    if ($res) $candidateCount = $res->fetch_assoc()['total'];

    $res = $conn->query("SELECT COUNT(*) as total FROM Job_List");
    if ($res) $jobCount = $res->fetch_assoc()['total'];

    // Today's Stats for "News" Awareness
    $today = date('Y-m-d');
    $res = $conn->query("SELECT COUNT(*) as total FROM candidates WHERE DATE(created_at) = '$today'");
    if ($res) $candidatesToday = $res->fetch_assoc()['total'];

    $res = $conn->query("SELECT COUNT(*) as total FROM Job_List WHERE DATE(created_at) = '$today'");
    if ($res) $jobsToday = $res->fetch_assoc()['total'];

    /*
       3. Build Context: DYNAMICALLY read Database Schema from INFORMATION_SCHEMA
       Plus semantic aliases from column_aliases table for smart understanding
    */
    $dynamicSchema = "\n\nDYNAMIC DATABASE SCHEMA (from live database):\n";
    $schemaQuery = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME";
    $schemaResult = $conn->query($schemaQuery);

    // Build alias map from database
    $aliasMap = [];
    $aliasQuery = $conn->query("SELECT table_name, actual_column_name, GROUP_CONCAT(alias) as aliases FROM column_aliases GROUP BY table_name, actual_column_name");
    while ($aliasRow = $aliasQuery->fetch_assoc()) {
        $aliasMap[$aliasRow['table_name']][$aliasRow['actual_column_name']] = explode(',', $aliasRow['aliases']);
    }

    while ($tableRow = $schemaResult->fetch_assoc()) {
        $tableName = $tableRow['TABLE_NAME'];
        $dynamicSchema .= "\nTable `$tableName`:\n";

        $colQuery = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableName'";
        $colResult = $conn->query($colQuery);

        while ($colRow = $colResult->fetch_assoc()) {
            $colName = $colRow['COLUMN_NAME'];
            $colType = $colRow['COLUMN_TYPE'];
            $dynamicSchema .= "  - `$colName` ($colType)";

            // Add aliases if they exist
            if (isset($aliasMap[$tableName][$colName])) {
                $aliases = $aliasMap[$tableName][$colName];
                $dynamicSchema .= " | also called: " . implode(", ", $aliases);
            }
            $dynamicSchema .= "\n";
        }
    }

    $dbSchema = "
    You are a highly intelligent AI Chat Agent for the CV Sorting system.
    Your goal is to be as helpful as ChatGPT. Provide detailed, insightful answers.

    CURRENT DATE: " . date('l, F j, Y') . "
    CURRENT TICKET: $ticketNo

    SYSTEM LIVE STATISTICS:
    - Total Employees: $employeeCount
    - Total Applicants/Candidates: $candidateCount ($candidatesToday added today)
    - Total Jobs/Tasks: $jobCount ($jobsToday created today)

    USER CONTEXT:
    - Employee ID: \"$callerId\"
    - Role: \"$callerRole\"
    " . $dynamicSchema . "

    SQL GENERATION RULES:
    - Always use backticks for table and column names.
    - Generate queries based on the actual schema above.
    - Use backticks for reserved keywords like \`match\`, \`status\`, \`key\`, \`value\`.
    - For numeric IDs with patterns (e.g., 'task 161'), add prefix format if detected (e.g., 'TASK161').
    - Always JOIN tables when needed for complete context.
    - Output ONLY valid MySQL SELECT statements.
    ";

    // 4. Determine Action: SQL, SEARCH, or NO_ACTION
    
    // --- STEP A: Decision ---
    $systemPromptStep1 = "
    You are a system command generator.
    Based on the User Question and Database Schema, output ONLY one of the following:

    1. A RAW MySQL SELECT statement (if the user asks for ANY data, information, status, count, list, or summary).
    2. SEARCH: [query] (if it is external world knowledge not in the database).
    3. NO_ACTION (ONLY for pure greetings like 'hello', 'hi', 'how are you' - NOT for data questions).

    IMPORTANT: If user asks about data AT ALL (task status, candidate info, employee details, etc), generate SQL.
    ONLY return NO_ACTION for pure social questions, NOT for information requests.

    SEMANTIC ALIASES: User may use different names for columns:
    - 'phone' / 'contact' = 'mobile_no'
    - 'email' / 'mail' = 'email_id'
    - 'experience' / 'exp' = 'total_experience'
    - 'task 161' / 'task_no 161' = WHERE \`task_no\` = 'TASK161'

    RULES:
    - Always wrap names in backticks: \`Job_List\`, \`task_no\`, \`mobile_no\`
    - Task numbers: convert 161 to TASK161 format
    - Output ONLY raw SQL, no text, brackets, or labels.";
    
    $messagesStep1 = array_merge(
        [["role" => "system", "content" => $systemPromptStep1]],
        $chatHistoryContext,
        [["role" => "user", "content" => $userMessage]]
    );

    $responseStep1 = callOpenAI($openaiApiKey, $messagesStep1);
    error_log("RAW AI Step 1 Response: " . $responseStep1);
    
    // Robust cleaning of the response
    $action = trim($responseStep1);
    
    // Remove common AI prefixes/labels (even if not at the start)
    $action = preg_replace('/\[VALID SQL\]\s*/i', '', $action);
    $action = preg_replace('/^```sql\s*|\s*```$/i', '', $action);
    $action = trim($action);

    // If it's a multi-line conversational response that contains a SELECT, try to extract just the SELECT
    if (stripos($action, 'SELECT ') !== false && stripos($action, 'SELECT ') !== 0) {
        $action = substr($action, stripos($action, 'SELECT '));
    }

    // ROBUSTNESS: Normalize the action type
    $isSql = (stripos($action, 'SELECT ') === 0);
    $isSearch = (stripos($action, 'SEARCH:') === 0);
    $isNoAction = (stripos($action, 'NO_ACTION') !== false || (!$isSql && !$isSearch));

    if ($isNoAction) {
        $action = "NO_ACTION";
    } elseif ($isSearch) {
        // Keep the SEARCH: part but normalized
        if (stripos($action, 'SEARCH:') !== 0) {
            $action = "SEARCH:" . trim(substr($action, stripos($action, 'SEARCH:') + 7));
        }
    }
    
    // Final check: if it looks like SQL but isn't explicitly NO_ACTION or SEARCH
    // we already handled it in the $isNoAction fall-through.
    
    // Log the final determined action for debugging
    error_log("Final AI Action Normalized: " . $action);
    
    $dbContextData = "";

    if ($action === "NO_ACTION") {
        $dbContextData = "No context needed.";
    } elseif (stripos($action, 'SEARCH:') === 0) {
        $searchQuery = trim(substr($action, 7));
        if (empty($serperApiKey)) {
            $dbContextData = "Note: A web search was requested, but the Serper Search API key is not configured in the system.";
        } else {
            $dbContextData = callSerperSearch($serperApiKey, $searchQuery);
        }
    } else {
        // Assume SQL
        $sql = $action;

        // Basic safety check on the SQL statement (ONLY allow SELECT)
        if (stripos($sql, 'SELECT ') !== 0) {
             throw new Exception("I am restricted to read-only queries. The requested operation was blocked.");
        }

        // VALIDATION: Fix common AI mistakes with table names and structure
        // Fix table name issues
        $sql = preg_replace('/\btask_list\b/i', '`Job_List`', $sql);
        $sql = preg_replace('/\btasklist\b/i', '`Job_List`', $sql);
        $sql = preg_replace('/FROM\s+Job_List(?!`)/i', 'FROM `Job_List`', $sql);
        $sql = preg_replace('/JOIN\s+Job_List(?!`)/i', 'JOIN `Job_List`', $sql);

        // Wrap common column names in backticks if not already wrapped
        $sql = preg_replace('/WHERE\s+task_no\s*=/i', 'WHERE `task_no` =', $sql);
        $sql = preg_replace('/WHERE\s+jd_id\s*=/i', 'WHERE `jd_id` =', $sql);
        $sql = preg_replace('/WHERE\s+status\s*=/i', 'WHERE `status` =', $sql);

        // Convert numeric task numbers to TASK format (e.g., 161 -> TASK161)
        // Handle both WHERE task_no = 161 and WHERE task_no = '161'
        $sql = preg_replace_callback('/WHERE\s+`task_no`\s*=\s*[\'"]?(\d+)[\'"]?/i', function($matches) {
            return 'WHERE `task_no` = \'TASK' . $matches[1] . '\'';
        }, $sql);

        // Also handle cases where task_no might not be wrapped in backticks
        $sql = preg_replace_callback('/WHERE\s+task_no\s*=\s*[\'"]?(\d+)[\'"]?/i', function($matches) {
            return 'WHERE `task_no` = \'TASK' . $matches[1] . '\'';
        }, $sql);

        error_log("SQL after validation: " . $sql);

        // Execute the generated SQL query safely
        $queryResult = $conn->query($sql);
        error_log("Query result rows: " . ($queryResult ? $queryResult->num_rows : 'NULL'));

        if ($queryResult) {
            $rows = [];
            while ($row = $queryResult->fetch_assoc()) {
                $rows[] = $row;
            }
            // Limit the context size sent back to prevent massive token usage
            if (count($rows) > 50) {
                $rows = array_slice($rows, 0, 50);
                $dbContextData = json_encode($rows) . "\n(Note: Results were truncated to 50 rows due to size limits.)";
            } else {
                $dbContextData = json_encode($rows);
            }
        } else {
            error_log("SQL ERROR: " . $conn->error . " | SQL: " . $sql);
            $dbContextData = "The database query resulted in an error: " . $conn->error . "\nSQL attempted: " . $sql;
        }
    }

    // --- STEP B: Generate Final Natural Language Answer ---
    $systemArchitecture = "
    SYSTEM ARCHITECTURE & FEATURES (USE THIS FOR NON-DATABASE QUESTIONS):
    - **Architecture**: PHP/MySQL web application with a collapsed sidebar layout and premium CSS.
    - **Authentication**: Custom session-based login with 'Remember Me' (cookie-based).
    - **RBAC (Permissions)**: Granular permission system. Roles include `sub-admin`, `admin`, and `super-admin`. Permissions include `edit_user`, `db_control`, `rpa_config`, etc. Users can only access features they have permissions for.
    - **RPA Module**: A dedicated configuration tool for robotic process automation. It allows management of external bots and triggering N8N webhooks for CV screening tasks.
    - **DB Control**: A powerful internal administrative tool for direct database manipulation. It supports CRUD on any table, adding new columns, creating tables, and has an integrated SQL console for super-admins.
    - **Ticketing & History**: Every conversation is tracked via a unique Ticket ID and saved for context.
    - **Flow**: User uploads CV/JD -> JD ID links them -> N8N screening -> results saved to `candidates` table.
    ";
    $systemPromptStep2 = $dbSchema . "\n\n" . $systemArchitecture . "\n\nYou are the AI Chat Agent. You HAVE just performed a data action (SQL or Search) and the results are provided in the 'Context Data' below.
    
    FORMATTING RULES:
    1. Use **bold** for names, quantities, and important values.
    2. Use bullet points or numbered lists for multiple items.
    3. Keep it professional and helpful.
    4. If Context Data contains database rows, treat them as the absolute truth for this system.
    5. If the user asks for a count, and you have rows, count them and provide the number.
    6. For \"best fit\" analysis, compare candidates based on skills, experience, and match percentage found in the context. Be descriptive about WHY a candidate is a good fit.
    7. TICKET CLOSURE: ONLY if the user explicitly says \"thank you\", \"I am done\", \"close it\", or \"goodbye\", append `[CLOSE_TICKET]` at the very end of your message.";
    
    $userPromptStep2 = "User Question (refer to history context if needed): " . $userMessage . "\n\nContext Data (Results of your action):\n" . $dbContextData . "\n\nPlease write a human-readable response based on this data. If the query returns no results, provide a helpful message explaining that the requested item was not found.";

    $messagesStep2 = array_merge(
        [["role" => "system", "content" => $systemPromptStep2]],
        $chatHistoryContext,
        [["role" => "user", "content" => $userPromptStep2]]
    );

    $finalAnswer = callOpenAI($openaiApiKey, $messagesStep2, true); // Use higher temperature for natural response
    
    // Check for ticket closure
    if (strpos($finalAnswer, '[CLOSE_TICKET]') !== false) {
        $finalAnswer = str_replace('[CLOSE_TICKET]', '', $finalAnswer);
        $conn->query("UPDATE chat_tickets SET status = 'Closed', closed_at = NOW() WHERE id = $ticketId");
        $finalAnswer .= "\n\n*Ticket $ticketNo has been closed.*";
    }

    // Save AI response to history
    $save_ai_stmt = $conn->prepare("INSERT INTO chat_history (ticket_id, sender, message) VALUES (?, 'AI', ?)");
    $save_ai_stmt->bind_param("is", $ticketId, $finalAnswer);
    $save_ai_stmt->execute();
    $save_ai_stmt->close();

    echo json_encode(['status' => 'success', 'data' => $finalAnswer, 'ticket_no' => $ticketNo]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Search helper using Serper.dev
function callSerperSearch($apiKey, $query) {
    $url = "https://google.serper.dev/search";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['q' => $query, 'gl' => 'bd']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) return "Search error: " . $err;
    
    $data = json_decode($response, true);
    $snippets = [];
    if (isset($data['organic'])) {
        foreach ($data['organic'] as $result) {
            $snippets[] = "Title: " . $result['title'] . "\nSnippet: " . $result['snippet'] . "\nLink: " . $result['link'];
        }
    }
    
    return empty($snippets) ? "No web results found for '$query'." : implode("\n\n---\n\n", array_slice($snippets, 0, 4));
}

// Helper function to call OpenAI ChatCompletion API
function callOpenAI($apiKey, $messages, $isNaturalResponse = false) {
    $url = "https://api.openai.com/v1/chat/completions";
    
    $postData = [
        "model" => "gpt-4o",
        "messages" => $messages,
        "temperature" => $isNaturalResponse ? 0.7 : 0.0 // 0.0 for SQL (deterministic), 0.7 for chat
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout for gpt-4o
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception("CURL Error API: " . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        $errObj = json_decode($response, true);
        $errMsg = $errObj['error']['message'] ?? 'Unknown API Error';
        throw new Exception("OpenAI API Error ($httpCode): " . $errMsg);
    }
    
    $responseData = json_decode($response, true);
    return $responseData['choices'][0]['message']['content'] ?? '';
}

