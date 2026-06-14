<?php
include __DIR__ . '/../config/db.php';

// 1. Create employees table
$sql = "CREATE TABLE IF NOT EXISTS employees (
    employee_id VARCHAR(50) PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    ip_no VARCHAR(50),
    mobile_no VARCHAR(20),
    designation VARCHAR(100),
    department VARCHAR(100),
    email VARCHAR(255),
    floor VARCHAR(50)
)";

if ($conn->query($sql)) {
    echo "Employees table created or already exists.\n";
} else {
    die("Error creating employees table: " . $conn->error);
}

// 2. Seed employees data
$employees = [
    ['5462', 'Asadul Hashem', '11203', '1730780620', 'Sr. GM', 'IT', 'asadul.hashem@mgi.org', '7th'],
    ['1457', 'Anupam Barua', '11304', '1713148370', 'Sr. GM', 'ACC', 'anupam@mgi.org', '7th'],
    ['8645', 'Maniruzzaman Chowdhury', '11204', '1755511588', 'Sr. DGM', 'IT', 'maniruzzaman.chowdhury@mgi.org', '7th'],
    ['485', 'Gouranga Kumar Saha', '11234', '1713015843', 'Sr. AGM', 'ECT', 'gouranga.saha@mgi.org', '7th'],
    ['6181', 'Tawhid Uddin Ahmed', '11244', '1714037682', 'AGM', 'ECT', 'tawhidpp@mgi.org', '7th'],
    ['5121', 'Hasib Choudhury', '11254', '1711224527', 'AGM', 'ECT', 'hasib.choudhury@mgi.org', '7th'],
    ['3538', 'SK. Tariqul Alam', '11242', '1713256838', 'AGM', 'ECT', 'tariqscm@mgi.org', '7th'],
    ['3797', 'Md. Uzzal Miah', '11229', '1713365196', 'AGM', 'ECT', 'uzzalmiah@mgi.org', '7th'],
    ['1203', 'Mosiur Rahman', '21311', '1714166870', 'AGM', 'IT', 'mosiur_it@mgi.org', '7th'],
    ['6542', 'Salauddin Ifte', '11224', '1766698878', 'AGM', 'IT', 'ifte_it@mgi.org', '7th'],
    ['63945', 'Md. Abdullah-Al-Mamun', '11226', '1321150033', 'Manager', 'IT', 'md.abdullah-al-mamun@mgi.org', '7th'],
    ['41962', 'Tanvir Ahmed Siddique', '11223', '1700703185', 'Sr.Manager', 'IT', 'ahmed.siddiq@mgi.org', '7th'],
    ['121269', 'Md. Arif Uddin', '11220', '1708458452', 'Manager', 'IT', 'arif.uddin@mgi.org', '7th'],
    ['34921', 'Shubhra Kumar Sen', '11230', '1713205069', 'Manager', 'IT', 'shubhra.sen@mgi.org', '7th'],
    ['5131', 'Md. Abdul Hannan Bhuyan', '21310', '1755543529', 'Dy. Manager', 'IT', 'hannan.bhuyan@mgi.org', '7th'],
    ['10777', 'Md. Alfaj Hossen', '11222', '1896014671', 'Sr. Executive', 'IT', 'alfaj.hossen@mgi.org', '7th'],
    ['475', 'Md. Abdur Rob', '11232', '1730789154', 'Sr. Manager', 'ECT', 'arobaccho@mgi.org', '7th'],
    ['3789', 'Md. Kuddos Mia', '11240', '1924790195', 'Sr. Executive', 'ECT', 'abdul.kuddos@mgi.org', '7th'],
    ['76903', 'Md Rashadul Islam', '11247', '1712927254', 'Sr. Executive', 'ECT', 'rashadul.islam@mgi.org', '7th'],
    ['3099', 'Md. Ebad Morshed', '11248', '1819607500', 'Manager', 'ECT', 'ebad_acc@mgi.org', '7th'],
    ['910', 'Md. Faruque Hossain', '11260', '1818549510', 'Manager', 'ECT', 'faruque.hossain@mgi.org', '7th'],
    ['45767', 'Md. Golam Mostafa Rana', '11249', '1789914206', 'Dy. Manager', 'ECT', 'mostafa.rana@mgi.org', '7th'],
    ['84857', 'Asit Sikder', '11255', '1894887932', 'Asst. Manager', 'ECT', 'asit.sikder@mgi.org', '7th'],
    ['162328', 'Md. Golam Sorowar', '11237', '1894721706', 'Dy. Manager', 'ECT', 'golam.sorowar@mgi.org', '7th'],
    ['110073', 'Md. Aktaruzzaman Sarker', '11235', '1894921843', 'Executive', 'ECT', 'aktaruzzaman.sarker@mgi.org', '7th'],
    ['110217', 'Saleh Muhammed Saki', '11256', '1894921845', 'Executive', 'ECT', 'saleh.muhammed@mgi.org', '7th'],
    ['4178', 'Sanjoy Kumer Biswas', '11233', '1715004286', 'Dy. Manager', 'ECT', 'sanjoy_accho@mgi.org', '7th'],
    ['122956', 'Arafat Hossain', '11252', '1894888037', 'Sr. Executive', 'ECT', 'arafat.hossain@mgi.org', '7th'],
    ['122955', 'Md Tariqul Islam', '11245', '1896014603', 'Sr. Executive', 'ECT', 'md.tariqul@mgi.org', '7th'],
    ['128985', 'Md. Akram-Ul-Islam', '11228', '1894721650', 'Sr. Executive', 'ECT', 'akram.islam@mgi.org', '7th'],
    ['146828', 'Kaushik Ghosh', '11227', '1894721651', 'Sr. Executive', 'ECT', 'kaushik.ghosh@mgi.org', '7th'],
    ['158158', 'Mohammad Nahidul Islam', '11246', '1894721951', 'Sr. Executive', 'ECT', 'md-nahidul.islam@mgi.org', '7th'],
    ['158608', 'Mazumder Mohit Hasnain', '11241', '1894887792', 'Executive', 'ECT', 'mohit.hasnain@mgi.org', '7th'],
    ['2872', 'Khalead Mahmud', '11225', '1896014672', 'Manager', 'IT (purchase)', 'khaledpp@mgi.org', '7th'],
];

$stmt = $conn->prepare("INSERT IGNORE INTO employees (employee_id, full_name, ip_no, mobile_no, designation, department, email, floor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($employees as $emp) {
    $stmt->bind_param("ssssssss", $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6], $emp[7]);
    $stmt->execute();
}
$stmt->close();
echo "Employees seeded successfully.\n";

// 3. Update users table schema safely
function addColumnSafe($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result->num_rows == 0) {
        if ($conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
            echo "Column '$column' added to '$table'.\n";
        } else {
            echo "Error adding column '$column': " . $conn->error . "\n";
        }
    } else {
        echo "Column '$column' already exists in '$table'.\n";
    }
}

addColumnSafe($conn, 'users', 'employee_id', "VARCHAR(50) UNIQUE AFTER id");
addColumnSafe($conn, 'users', 'profile_pic', "VARCHAR(255) AFTER role");

// Modify status enum
$conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('active', 'blocked', 'pending') DEFAULT 'pending'");

// Update existing admin status to active
$conn->query("UPDATE users SET status = 'active' WHERE role = 'admin'");

echo "Users table updated successfully.\n";
$conn->close();
?>
