<?php
include __DIR__ . '/../config/db.php';

// 1. Clear existing user and employee data
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE users");
$conn->query("TRUNCATE TABLE employees");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "Existing users and employees cleared.\n";

// 2. Define employee master list
$employees = [
    ['005462', 'Asadul Hashem', '11203', '01730780620', 'Sr. GM', 'IT', 'asadul.hashem@mgi.org', '7th'],
    ['001457', 'Anupam Barua', '11304', '01713148370', 'Sr. GM', 'ACC', 'anupam@mgi.org', '7th'],
    ['008645', 'Maniruzzaman Chowdhury', '11204', '01755511588', 'Sr. DGM', 'IT', 'maniruzzaman.chowdhury@mgi.org', '7th'],
    ['000485', 'Gouranga Kumar Saha', '11234', '01713015843', 'Sr. AGM', 'ECT', 'gouranga.saha@mgi.org', '7th'],
    ['006181', 'Tawhid Uddin Ahmed', '11244', '01714037682', 'AGM', 'ECT', 'tawhidpp@mgi.org', '7th'],
    ['005121', 'Hasib Choudhury', '11254', '01711224527', 'AGM', 'ECT', 'hasib.choudhury@mgi.org', '7th'],
    ['003538', 'SK. Tariqul Alam', '11242', '01713256838', 'AGM', 'ECT', 'tariqscm@mgi.org', '7th'],
    ['003797', 'Md. Uzzal Miah', '11229', '01713365196', 'AGM', 'ECT', 'uzzalmiah@mgi.org', '7th'],
    ['001203', 'Mosiur Rahman', '21311', '01714166870', 'AGM', 'IT', 'mosiur_it@mgi.org', '7th'],
    ['006542', 'Salauddin Ifte', '11224', '01766698878', 'AGM', 'IT', 'ifte_it@mgi.org', '7th'],
    ['063945', 'Md. Abdullah-Al-Mamun', '11226', '01321150033', 'Manager', 'IT', 'md.abdullah-al-mamun@mgi.org', '7th'],
    ['041962', 'Tanvir Ahmed Siddique', '11223', '01700703185', 'Sr.Manager', 'IT', 'ahmed.siddiq@mgi.org', '7th'],
    ['121269', 'Md. Arif Uddin', '11220', '01708458452', 'Manager', 'IT', 'arif.uddin@mgi.org', '7th'],
    ['034921', 'Shubhra Kumar Sen', '11230', '01713205069', 'Manager', 'IT', 'shubhra.sen@mgi.org', '7th'],
    ['005131', 'Md. Abdul Hannan Bhuyan', '21310', '01755543529', 'Dy. Manager', 'IT', 'hannan.bhuyan@mgi.org', '7th'],
    ['010777', 'Md. Alfaj Hossen', '11222', '01896014671', 'Sr. Executive', 'IT', 'alfaj.hossen@mgi.org', '7th'],
    ['000475', 'Md. Abdur Rob', '11232', '01730789154', 'Sr. Manager', 'ECT', 'arobaccho@mgi.org', '7th'],
    ['003789', 'Md. Kuddos Mia', '11240', '01924790195', 'Sr. Executive', 'ECT', 'abdul.kuddos@mgi.org', '7th'],
    ['076903', 'Md Rashadul Islam', '11247', '01712927254', 'Sr. Executive', 'ECT', 'rashadul.islam@mgi.org', '7th'],
    ['003099', 'Md. Ebad Morshed', '11248', '01819607500', 'Manager', 'ECT', 'ebad_acc@mgi.org', '7th'],
    ['000910', 'Md. Faruque Hossain', '11260', '01818549510', 'Manager', 'ECT', 'faruque.hossain@mgi.org', '7th'],
    ['045767', 'Md. Golam Mostafa Rana', '11249', '01789914206', 'Dy. Manager', 'ECT', 'mostafa.rana@mgi.org', '7th'],
    ['084857', 'Asit Sikder', '11255', '01894887932', 'Asst. Manager', 'ECT', 'asit.sikder@mgi.org', '7th'],
    ['162328', 'Md. Golam Sorowar', '11237', '01894721706', 'Dy. Manager', 'ECT', 'golam.sorowar@mgi.org', '7th'],
    ['110073', 'Md. Aktaruzzaman Sarker', '11235', '01894921843', 'Executive', 'ECT', 'aktaruzzaman.sarker@mgi.org', '7th'],
    ['110217', 'Saleh Muhammed Saki', '11256', '01894921845', 'Executive', 'ECT', 'saleh.muhammed@mgi.org', '7th'],
    ['004178', 'Sanjoy Kumer Biswas', '11233', '01715004286', 'Dy. Manager', 'ECT', 'sanjoy_accho@mgi.org', '7th'],
    ['122956', 'Arafat Hossain', '11252', '01894888037', 'Sr. Executive', 'ECT', 'arafat.hossain@mgi.org', '7th'],
    ['122955', 'Md Tariqul Islam', '11245', '01896014603', 'Sr. Executive', 'ECT', 'md.tariqul@mgi.org', '7th'],
    ['128985', 'Md. Akram-Ul-Islam', '11228', '01894721650', 'Sr. Executive', 'ECT', 'akram.islam@mgi.org', '7th'],
    ['146828', 'Kaushik Ghosh', '11227', '01894721651', 'Sr. Executive', 'ECT', 'kaushik.ghosh@mgi.org', '7th'],
    ['158158', 'Mohammad Nahidul Islam', '11246', '01894721951', 'Sr. Executive', 'ECT', 'md-nahidul.islam@mgi.org', '7th'],
    ['158608', 'Mazumder Mohit Hasnain', '11241', '01894887792', 'Executive', 'ECT', 'mohit.hasnain@mgi.org', '7th'],
    ['002872', 'Khalead Mahmud', '11225', '01896014672', 'Manager', 'IT (purchase)', 'khaledpp@mgi.org', '7th'],
    ['097727', 'Joy Ballav', '11239', '01894888308', 'Asst. Manager', 'IT (RPA)', 'joy.ballav@mgi.org', '7th'],
    ['161608', 'Utsha Dhar', '', '01894888359', 'RPA Developer', 'IT (RPA)', 'utsha.dhar@mgi.org', '7th'],
    ['098542', 'Mahbubul Islam', '11275', '01894888328', 'Dy. Manager', 'IT (Web-Apps)', 'mahbubul.islam@mgi.org', '7th'],
    ['117564', 'Md Ayaub Ali', '11276', '01894921952', 'Asst. Manager', 'IT', 'ayaub.ali@mgi.org', '7th'],
    ['111763', 'Sagar Chakraborty', '11258', '01755664444', 'Asst. Manager', 'IT (IOT)', 'sagar.chakraborty@mgi.org', '7th'],
    ['159219', 'Md. Osman Gani', '11259', '01755664433', 'Dy. Manager', 'IT (IOT)', 'osman.gani@mgi.org', '7th'],
    ['097063', 'Md. Zubair Bin Tareque', '11267', '01894888301', 'Dy. Manager', 'IT (Web-Dev.)', 'zubair.tareque@mgi.org', '7th'],
    ['120292', 'Uzzal Kar', '', '01896014581', 'Asst. Manager', 'IT (Web-Dev.)', 'uzzal.kar@mgi.org', '7th'],
    ['122287', 'Sharifur Rahman', '', '01321150180', 'Dy. Manager', 'IT (Web-Apps)', 'sharifur.rahman@mgi.org', '7th'],
    ['118210', 'Sheikh Asif Iqbal', '11277', '01894921971', 'Sr. Executive', 'IT', 'asif.iqbal@mgi.org', '7th'],
    ['121442', 'Ishtiaque Rahman', '', '01321150121', 'Asst. Manager', 'IT (M.Apps)', 'ishtiaque.rahman@mgi.org', '7th'],
];

// 3. Insert Employees
$stmt = $conn->prepare("INSERT INTO employees (employee_id, full_name, ip_no, mobile_no, designation, department, email, floor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($employees as $emp) {
    $stmt->bind_param("ssssssss", $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6], $emp[7]);
    $stmt->execute();
}
$stmt->close();
echo count($employees) . " employees synced.\n";

// 4. Create Admin User (097727)
$admin_id = '097727';
$admin_user = 'joy.ballav'; // Display username
$admin_pass = password_hash('admin', PASSWORD_DEFAULT);
$admin_role = 'admin';
$admin_status = 'active';

$stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $admin_id, $admin_user, $admin_pass, $admin_role, $admin_status);
if ($stmt->execute()) {
    echo "Primary admin user (097727) created.\n";
} else {
    echo "Error creating admin user: " . $stmt->error . "\n";
}
$stmt->close();

// 5. Update Job_List.created_by to 097727
if ($conn->query("UPDATE Job_List SET created_by = '097727'")) {
    echo "Job_List updated with new creator ID (097727).\n";
} else {
    echo "Error updating Job_List: " . $conn->error . "\n";
}

$conn->close();
echo "Migration complete.\n";
?>
