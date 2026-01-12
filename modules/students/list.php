<?php
/**
 * Student List/Directory
 * View and manage all students
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Students - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Get filter parameters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$sectionFilter = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : 'active';
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build SQL query
$whereClauses = ["1=1"];
$params = [];

if ($classFilter) {
    $whereClauses[] = "c.id = ?";
    $params[] = $classFilter;
}

if ($sectionFilter) {
    $whereClauses[] = "s.id = ?";
    $params[] = $sectionFilter;
}

if ($statusFilter) {
    $whereClauses[] = "e.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereClauses[] = "(u.full_name LIKE ? OR st.student_id LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereSQL = implode(' AND ', $whereClauses);

// Get students with enrollment info
$sql = "SELECT st.id, st.student_id, u.full_name, u.email, u.photo,
               c.class_name, s.section_name, e.roll_number, e.status, e.enrollment_date,
               st.date_of_birth, st.gender
        FROM students st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN enrollments e ON st.id = e.student_id
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE $whereSQL
        GROUP BY st.id
        ORDER BY c.class_numeric, s.section_name, e.roll_number, u.full_name
        LIMIT $perPage OFFSET $offset";

$students = getAll($sql, $params);

// Get total count
$countSQL = "SELECT COUNT(DISTINCT st.id) as total
             FROM students st
             JOIN users u ON st.user_id = u.id
             LEFT JOIN enrollments e ON st.id = e.student_id
             LEFT JOIN sections s ON e.section_id = s.id
             LEFT JOIN classes c ON s.class_id = c.id
             WHERE $whereSQL";
$totalStudents = getValue($countSQL, $params);
$totalPages = ceil($totalStudents / $perPage);

// Get filters data
$classes = getAll("SELECT * FROM classes ORDER BY class_numeric");
$sections = getAll("SELECT s.*, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.class_numeric, s.section_name");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Students</h1>
            <p class="text-base-content/60 mt-1">View and manage student directory</p>
        </div>
        <div class="flex gap-2">
            <a href="enroll.php" class="btn btn-primary gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                Enroll Student
            </a>
            <a href="../users/create.php?type=student" class="btn btn-ghost gap-2">
                + New Student
            </a>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Search</span></label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           placeholder="Name or Student ID..." class="input input-bordered input-sm" />
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Class</span></label>
                    <select name="class" class="select select-bordered select-sm">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $classFilter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Section</span></label>
                    <select name="section" class="select select-bordered select-sm">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $sectionFilter == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select name="status" class="select select-bordered select-sm">
                        <option value="">All</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="withdrawn" <?php echo $statusFilter === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">&nbsp;</span></label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-1">Filter</button>
                        <a href="list.php" class="btn btn-ghost btn-sm">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Students</div>
            <div class="stat-value text-primary"><?php echo $totalStudents; ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Active</div>
            <div class="stat-value text-success">
                <?php echo getValue("SELECT COUNT(*) FROM enrollments WHERE status = 'active'"); ?>
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">Not Enrolled</div>
            <div class="stat-value text-warning">
                <?php echo getValue("SELECT COUNT(*) FROM students WHERE id NOT IN (SELECT student_id FROM enrollments WHERE status = 'active')"); ?>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <?php if (empty($students)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No students found matching your criteria.</span>
        </div>
    <?php else: ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-0">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Class/Section</th>
                                <th>Roll No.</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="w-10 h-10 rounded-full <?php echo $student['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                                                    <?php if ($student['photo']): ?>
                                                        <img src="<?php echo APP_URL . '/' . $student['photo']; ?>" alt="" />
                                                    <?php else: ?>
                                                        <span><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                <div class="text-sm text-base-content/60"><?php echo htmlspecialchars($student['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="font-mono text-sm"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td>
                                        <?php if ($student['class_name']): ?>
                                            <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?>
                                        <?php else: ?>
                                            <span class="text-base-content/60">Not enrolled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $student['roll_number'] ? str_pad($student['roll_number'], 2, '0', STR_PAD_LEFT) : '-'; ?>
                                    </td>
                                    <td><?php echo $student['gender'] ? ucfirst($student['gender']) : '-'; ?></td>
                                    <td>
                                        <?php if ($student['status'] === 'active'): ?>
                                            <span class="badge badge-success badge-sm">Active</span>
                                        <?php elseif ($student['status'] === 'inactive'): ?>
                                            <span class="badge badge-warning badge-sm">Inactive</span>
                                        <?php elseif ($student['status'] === 'withdrawn'): ?>
                                            <span class="badge badge-error badge-sm">Withdrawn</span>
                                        <?php else: ?>
                                            <span class="badge badge-ghost badge-sm">No enrollment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-xs btn-ghost">View</a>
                                            <?php if (!$student['class_name']): ?>
                                                <a href="enroll.php?student_id=<?php echo $student['id']; ?>" class="btn btn-xs btn-primary">Enroll</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <?php 
                $baseUrl = 'list.php?status=' . urlencode($statusFilter) . '&search=' . urlencode($searchQuery) . 
                          '&class=' . $classFilter . '&section=' . $sectionFilter;
                echo getPagination($page, $totalPages, $baseUrl);
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
