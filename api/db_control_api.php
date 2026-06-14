<?php
include __DIR__ . '/../config/db.php';
include __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

// Strict check for permissions
if (!has_permission('db_control')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: DB Control permission required.']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list_tables':
        $result = $conn->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        echo json_encode(['status' => 'success', 'data' => $tables]);
        break;

    case 'table_details':
        $table = $_GET['table'] ?? '';
        if (!$table) {
            echo json_encode(['status' => 'error', 'message' => 'Table name required.']);
            break;
        }

        // Get Columns
        $resColumns = $conn->query("SHOW COLUMNS FROM `$table`");
        $columns = [];
        while ($col = $resColumns->fetch_assoc()) {
            $columns[] = $col;
        }

        // Get record count
        $resCount = $conn->query("SELECT COUNT(*) as total FROM `$table`");
        $countRow = $resCount->fetch_assoc();

        echo json_encode([
            'status' => 'success',
            'data' => [
                'columns' => $columns,
                'total_records' => $countRow['total']
            ]
        ]);
        break;

    case 'list_data':
        $table = $_GET['table'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;

        if (!$table) {
            echo json_encode(['status' => 'error', 'message' => 'Table name required.']);
            break;
        }

        $result = $conn->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        echo json_encode(['status' => 'success', 'data' => $rows, 'page' => $page, 'limit' => $limit]);
        break;

    case 'execute_query':
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = trim($data['query'] ?? '');

        if (!$sql) {
            echo json_encode(['status' => 'error', 'message' => 'Query cannot be empty.']);
            break;
        }

        try {
            // Check if it's a SELECT query to handle results differently
            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESC') === 0) {
                $result = $conn->query($sql);
                if ($result === false) throw new Exception($conn->error);
                
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                echo json_encode(['status' => 'success', 'type' => 'select', 'data' => $data, 'count' => count($data)]);
            } else {
                // For non-select queries (UPDATE, DELETE, DROP, etc.)
                $result = $conn->query($sql);
                if ($result === false) throw new Exception($conn->error);
                
                echo json_encode([
                    'status' => 'success', 
                    'type' => 'execute', 
                    'affected_rows' => $conn->affected_rows,
                    'message' => 'Query executed successfully.'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'save_record':
        $data = json_decode(file_get_contents('php://input'), true);
        $table = $data['table'] ?? '';
        $values = $data['data'] ?? [];
        $pkField = $data['pk_field'] ?? '';
        $pkValue = $data['pk_value'] ?? null;

        if (!$table || empty($values)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
            break;
        }

        if ($pkField && $pkValue !== null) {
            // UPDATE
            $sets = [];
            foreach ($values as $col => $val) {
                $v = ($val === null) ? "NULL" : "'" . $conn->real_escape_string($val) . "'";
                $sets[] = "`$col` = $v";
            }
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pkField` = '" . $conn->real_escape_string($pkValue) . "'";
        } else {
            // INSERT
            $cols = [];
            $vals = [];
            foreach ($values as $col => $val) {
                $cols[] = "`$col`";
                $vals[] = ($val === null) ? "NULL" : "'" . $conn->real_escape_string($val) . "'";
            }
            $sql = "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        }

        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success', 'message' => 'Record saved.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    case 'delete_record':
        $data = json_decode(file_get_contents('php://input'), true);
        $table = $data['table'] ?? '';
        $pkField = $data['pk_field'] ?? '';
        $pkValue = $data['pk_value'] ?? '';

        if (!$table || !$pkField || $pkValue === '') {
            echo json_encode(['status' => 'error', 'message' => 'Missing ID to delete.']);
            break;
        }

        $sql = "DELETE FROM `$table` WHERE `$pkField` = '" . $conn->real_escape_string($pkValue) . "'";
        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success', 'message' => 'Record deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    case 'export_csv':
        $table = $_GET['table'] ?? '';
        if (!$table) exit('Table name required.');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$table.'_export_'.date('Y-m-d').'.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        $cols = [];
        while($row = $result->fetch_assoc()) $cols[] = $row['Field'];
        fputcsv($output, $cols);
        
        // Data
        $result = $conn->query("SELECT * FROM `$table` LIMIT 10000"); // Safety limit
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    case 'create_table':
        $data = json_decode(file_get_contents('php://input'), true);
        $table = $data['table'] ?? '';
        $columns = $data['columns'] ?? [];

        if (!$table || empty($columns)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing table name or column details.']);
            break;
        }

        // Basic sanitization for table name (alphanumeric and underscore)
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        
        $colDefs = [];
        foreach ($columns as $col) {
            $name = preg_replace('/[^A-Za-z0-9_]/', '', $col['name']);
            $type = $col['type']; // Types come from controlled select dropdowns
            if ($name) {
                $colDefs[] = "`$name` $type";
            }
        }

        if (empty($colDefs)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid column definitions.']);
            break;
        }

        $sql = "CREATE TABLE `$table` (" . implode(', ', $colDefs) . ")";
        
        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success', 'message' => "Table '$table' created successfully."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    case 'add_column':
        $data = json_decode(file_get_contents('php://input'), true);
        $table = $data['table'] ?? '';
        $name = $data['name'] ?? '';
        $type = $data['type'] ?? '';
        $default = $data['default_val'] ?? '';

        if (!$table || !$name || !$type) {
            echo json_encode(['status' => 'error', 'message' => 'Missing table, column name or type.']);
            break;
        }

        // Basic sanitization for column names (alphanumeric and underscore)
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name);

        $sql = "ALTER TABLE `$table` ADD COLUMN `$name` $type";
        if ($default !== '') {
            $sql .= " DEFAULT " . ($default === 'NULL' ? 'NULL' : "'" . $conn->real_escape_string($default) . "'");
        }

        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success', 'message' => "Column '$name' added successfully."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    case 'table_op':
        $data = json_decode(file_get_contents('php://input'), true);
        $table = $data['table'] ?? '';
        $op = $data['op'] ?? ''; // truncate or drop

        if (!$table || !in_array($op, ['truncate', 'drop'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation or table.']);
            break;
        }

        $sql = ($op === 'truncate') ? "TRUNCATE TABLE `$table`" : "DROP TABLE `$table`";
        if ($conn->query($sql)) {
            echo json_encode(['status' => 'success', 'message' => "Table $op operation completed."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}

$conn->close();
?>
