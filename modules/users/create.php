<?php
/**
 * Create New User
 * Admin can create users of any type
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Create User - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../classes/Auth.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $userType = sanitize($_POST['user_type']);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $photoPath = '';
        
        // Validate
        if (empty($fullName) || empty($email) || empty($userType)) {
            $errors[] = 'All required fields must be filled';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email address';
        } else {
            // Handle photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['photo'], UPLOAD_PATH . '/profiles', ['jpg', 'jpeg', 'png']);
                if ($uploadResult['success']) {
                    $photoPath = str_replace(BASE_PATH . '/', '', $uploadResult['path']);
                } else {
                    $errors[] = 'Photo upload failed: ' . $uploadResult['error'];
                }
            }
            
            if (empty($errors)) {
                try {
                    // Generate temporary password
                    $tempPassword = bin2hex(random_bytes(4)); // 8 character password
                    
                    // Create user
                    $userData = [
                        'email' => $email,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'password' => $tempPassword,
                        'role' => $userType
                    ];
                    
                    $result = Auth::register($userData);
                    
                    if ($result['success']) {
                        $userId = $result['user_id'];
                        
                        // Update photo if uploaded
                        if ($photoPath) {
                            query("UPDATE users SET photo = ? WHERE id = ?", [$photoPath, $userId]);
                        }
                        
                        // Create type-specific records
                        if ($userType === 'student') {
                            $studentId = 'STU' . date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);
                            $sql = "INSERT INTO students (user_id, student_id, date_of_birth, gender, address) VALUES (?, ?, ?, ?, ?)";
                            insert($sql, [
                                $userId,
                                $studentId,
                                $_POST['date_of_birth'] ?? null,
                                $_POST['gender'] ?? null,
                                $_POST['address'] ?? null
                            ]);
                        } elseif ($userType === 'teacher') {
                            $teacherId = 'TCH' . date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);
                            $sql = "INSERT INTO teachers (user_id, teacher_id, qualification, specialization, joining_date, employment_type) VALUES (?, ?, ?, ?, ?, ?)";
                            insert($sql, [
                                $userId,
                                $teacherId,
                                $_POST['qualification'] ?? null,
                                $_POST['specialization'] ?? null,
                                $_POST['joining_date'] ?? date('Y-m-d'),
                                $_POST['employment_type'] ?? 'full_time'
                            ]);
                        } elseif ($userType === 'parent') {
                            $sql = "INSERT INTO parents (user_id, occupation, relationship) VALUES (?, ?, ?)";
                            insert($sql, [
                                $userId,
                                $_POST['occupation'] ?? null,
                                $_POST['relationship'] ?? 'father'
                            ]);
                        }
                        
                        $success = "User created successfully! Temporary password: <strong>$tempPassword</strong> (Please save this and share with the user)";
                    } else {
                        $errors[] = $result['message'];
                    }
                } catch (Exception $e) {
                    $errors[] = 'Error creating user: ' . $e->getMessage();
                }
            }
        }
    }
}

$roles = getAll("SELECT * FROM roles WHERE role_name IN ('student', 'teacher', 'parent', 'admin') ORDER BY role_name");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Create New User</h1>
        <p class="text-base-content/60 mt-1">Add a new user to the system</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-6">
            <div>
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div><?php echo $success; ?></div>
            </div>
            <div>
                <a href="index.php" class="btn btn-sm">View Users</a>
                <a href="create.php" class="btn btn-sm btn-ghost">Add Another</a>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="createUserForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title">Basic Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">User Type *</span></label>
                        <select name="user_type" id="user_type" class="select select-bordered" required onchange="toggleTypeFields()">
                            <option value="">-- Select Type --</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="parent">Parent/Guardian</option>
                            <option value="admin">Admin/Staff</option>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Full Name *</span></label>
                        <input type="text" name="full_name" class="input input-bordered" required />
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Email *</span></label>
                        <input type="email" name="email" class="input input-bordered" required />
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Phone</span></label>
                        <input type="tel" name="phone" class="input input-bordered" />
                    </div>
                    
                    <div class="form-control md:col-span-2">
                        <label class="label"><span class="label-text">Profile Photo</span></label>
                        <input type="file" name="photo" accept="image/*" class="file-input file-input-bordered" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Specific Fields -->
        <div id="student_fields" class="card bg-base-100 shadow-xl mb-6" style="display:none;">
            <div class="card-body">
                <h2 class="card-title">Student Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Date of Birth</span></label>
                        <input type="date" name="date_of_birth" class="input input-bordered" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Gender</span></label>
                        <select name="gender" class="select select-bordered">
                            <option value="">-- Select --</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-control md:col-span-2">
                        <label class="label"><span class="label-text">Address</span></label>
                        <textarea name="address" class="textarea textarea-bordered"></textarea>
                    </div>
                </div>
                <div class="alert alert-info mt-4">
                    <span>Note: After creating the student, you can enroll them in a class from the Students menu.</span>
                </div>
            </div>
        </div>

        <!-- Teacher Specific Fields -->
        <div id="teacher_fields" class="card bg-base-100 shadow-xl mb-6" style="display:none;">
            <div class="card-body">
                <h2 class="card-title">Teacher Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Qualification</span></label>
                        <input type="text" name="qualification" placeholder="e.g., M.Ed, B.Sc" class="input input-bordered" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Specialization</span></label>
                        <input type="text" name="specialization" placeholder="e.g., Mathematics, Science" class="input input-bordered" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Joining Date</span></label>
                        <input type="date" name="joining_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Employment Type</span></label>
                        <select name="employment_type" class="select select-bordered">
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parent Specific Fields -->
        <div id="parent_fields" class="card bg-base-100 shadow-xl mb-6" style="display:none;">
            <div class="card-body">
                <h2 class="card-title">Parent/Guardian Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Occupation</span></label>
                        <input type="text" name="occupation" class="input input-bordered" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Relationship</span></label>
                        <select name="relationship" class="select select-bordered">
                            <option value="father">Father</option>
                            <option value="mother">Mother</option>
                            <option value="guardian">Guardian</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</main>

<script>
function toggleTypeFields() {
    const userType = document.getElementById('user_type').value;
    
    // Hide all type-specific fields
    document.getElementById('student_fields').style.display = 'none';
    document.getElementById('teacher_fields').style.display = 'none';
    document.getElementById('parent_fields').style.display = 'none';
    
    // Show relevant fields
    if (userType === 'student') {
        document.getElementById('student_fields').style.display = 'block';
    } else if (userType === 'teacher') {
        document.getElementById('teacher_fields').style.display = 'block';
    } else if (userType === 'parent') {
        document.getElementById('parent_fields').style.display = 'block';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
