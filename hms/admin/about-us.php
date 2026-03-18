<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
include('include/config.php');

// ============================================================
// ADMIN AUTH — fixed broken strlen() check
// ============================================================
$adminId = (int) ($_SESSION['id'] ?? 0);
if ($adminId <= 0) {
    header('Location: logout.php');
    exit;
}

$adminChk = $con->prepare("SELECT id, username FROM admin WHERE id = ?");
$adminChk->bind_param("i", $adminId);
$adminChk->execute();
$adminData = $adminChk->get_result()->fetch_assoc();
$adminChk->close();

if (!$adminData) {
    session_destroy();
    header('Location: logout.php');
    exit;
}
$adminName = $adminData['username'];

// ============================================================
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// HELPERS — session flash messages (Post-Redirect-Get)
// ============================================================
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ============================================================
// UNRESPONDED FEEDBACK COUNT — for sidebar badge
// ============================================================
$unreadStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus WHERE status = 'Not Responded'");
$unreadStmt->execute();
$totalUnread = (int) $unreadStmt->get_result()->fetch_assoc()['c'];
$unreadStmt->close();

// ============================================================
// HANDLE UPDATE — POST with CSRF + prepared statement
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $pageTitle = trim($_POST['pagetitle'] ?? '');
    $pageDes   = trim($_POST['pagedes']   ?? '');

    if (empty($pageTitle)) {
        setFlash('error', 'Page title cannot be empty.');
    } elseif (strlen($pageTitle) > 255) {
        setFlash('error', 'Page title must be 255 characters or fewer.');
    } elseif (empty($pageDes)) {
        setFlash('error', 'Page description cannot be empty.');
    } else {
        $updStmt = $con->prepare(
            "UPDATE tblpage SET PageTitle = ?, PageDescription = ? WHERE PageType = 'aboutus'"
        );
        $updStmt->bind_param("ss", $pageTitle, $pageDes);

        if ($updStmt->execute()) {
            setFlash('success', 'About Us content has been updated successfully.');
        } else {
            setFlash('error', 'Something went wrong. Please try again.');
        }
        $updStmt->close();
    }

    header('Location: about-us.php');
    exit;
}

// ============================================================
// FETCH CURRENT CONTENT — prepared statement
// ============================================================
$fetchStmt = $con->prepare(
    "SELECT PageTitle, PageDescription FROM tblpage WHERE PageType = 'aboutus' LIMIT 1"
);
$fetchStmt->execute();
$pageRow = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

$pageTitle = $pageRow['PageTitle']       ?? '';
$pageDes   = $pageRow['PageDescription'] ?? '';

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | About Us</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar {
        width: 280px;
        transition: all 0.3s ease;
        background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
    }
    .main-content {
        margin-left: 280px;
        transition: all 0.3s ease;
    }
    .nav-link { transition: all 0.3s ease; }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); }
    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        border-left: 4px solid #fff;
    }
    @media (max-width: 768px) {
        .sidebar { margin-left: -280px; }
        .sidebar.active { margin-left: 0; }
        .main-content { margin-left: 0; }
        .main-content.active { margin-left: 280px; }
    }
    /* Character counter */
    .char-counter { font-size: 12px; color: #6b7280; text-align: right; margin-top: 2px; }
    .char-counter.warn { color: #d97706; }
    .char-counter.over { color: #dc2626; }
    /* Rich text toolbar */
    .editor-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        padding: 8px;
        background: #f9fafb;
        border: 1px solid #d1d5db;
        border-bottom: none;
        border-radius: 8px 8px 0 0;
    }
    .editor-toolbar button {
        width: 30px; height: 30px;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        background: white;
        color: #374151;
        font-size: 13px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.1s, border-color 0.1s;
    }
    .editor-toolbar button:hover { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
    .editor-toolbar button.active { background: #dbeafe; border-color: #3b82f6; color: #1d4ed8; }
    .editor-toolbar .toolbar-sep { width: 1px; background: #e5e7eb; margin: 2px 4px; }
    #pagedes {
        border-radius: 0 0 8px 8px !important;
        border-top: none !important;
        resize: vertical;
        min-height: 280px;
    }
    </style>
</head>
<body class="bg-gray-50">

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <div class="sidebar fixed h-full text-white">
        <div class="p-5 bg-[#182848]">
            <h2 class="text-xl font-bold flex items-center space-x-2">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="w-7 h-7 object-contain">
                <span>BizTracker</span>
            </h2>
        </div>

        <!-- Admin Profile -->
        <div class="p-4 border-b border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                    <i class="fas fa-user-shield text-white"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-medium truncate"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-sm text-white/70">Administrator</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>
            <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage-users.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-users w-5 text-center"></i>
                <span>Users</span>
            </a>
            <a href="user-logs.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-file-alt w-5 text-center"></i>
                <span>User Session Logs</span>
            </a>
            <a href="manage_feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-envelope w-5 text-center"></i>
                <span>Feedback</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="ml-auto bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">
                        <?php echo $totalUnread; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="about-us.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-info-circle w-5 text-center"></i>
                <span>About Us</span>
            </a>
            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
            <a href="change-password.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-lock w-5 text-center"></i>
                <span>Change Password</span>
            </a>
            <a href="logout.php" onclick="return confirmLogout()" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Log Out</span>
            </a>
        </nav>
    </div>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <div class="main-content min-h-screen bg-gray-100">

        <!-- Header -->
        <header class="bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white">
            <div class="h-1 bg-white/10"></div>
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <button id="sidebarToggle" class="md:hidden text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-semibold">About Us</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">Admin</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">About Us Content</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8 max-w-4xl">

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="mb-6 p-4 rounded-lg flex items-center gap-3
                    <?php echo $flash['type'] === 'success'
                        ? 'bg-green-100 text-green-800 border border-green-200'
                        : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> flex-shrink-0"></i>
                    <span><?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Content Card -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-semibold text-gray-700">
                        About Us <span class="text-[#4b6cb7]">Content</span>
                    </h2>
                    <!-- Live preview toggle -->
                    <button type="button" id="previewToggle"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2 text-gray-600">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>

                <!-- Live Preview Panel (hidden by default) -->
                <div id="previewPanel" class="hidden mb-6 p-5 border border-blue-200 bg-blue-50 rounded-lg">
                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-3">
                        <i class="fas fa-eye mr-1"></i>Live Preview
                    </p>
                    <h3 id="previewTitle" class="text-xl font-bold text-gray-800 mb-3"></h3>
                    <div id="previewDesc" class="text-gray-600 text-sm leading-relaxed whitespace-pre-wrap"></div>
                </div>

                <form method="POST" action="about-us.php" class="space-y-6">
                    <!-- CSRF token -->
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Page Title -->
                    <div>
                        <label for="pagetitle" class="block text-sm font-medium text-gray-700 mb-1">
                            Page Title <span class="text-red-500">*</span>
                        </label>
                        <input id="pagetitle" name="pagetitle" type="text" maxlength="255"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                               value="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>"
                               required>
                        <div id="titleCounter" class="char-counter">0 / 255</div>
                    </div>

                    <!-- Page Description -->
                    <div>
                        <label for="pagedes" class="block text-sm font-medium text-gray-700 mb-1">
                            Page Description <span class="text-red-500">*</span>
                        </label>

                        <!-- Lightweight formatting toolbar -->
                        <div class="editor-toolbar">
                            <button type="button" title="Bold" onclick="wrapText('**','**')"><i class="fas fa-bold"></i></button>
                            <button type="button" title="Italic" onclick="wrapText('_','_')"><i class="fas fa-italic"></i></button>
                            <button type="button" title="Underline" onclick="wrapText('<u>','</u>')"><i class="fas fa-underline"></i></button>
                            <div class="toolbar-sep"></div>
                            <button type="button" title="Heading" onclick="wrapLine('## ')"><i class="fas fa-heading"></i></button>
                            <button type="button" title="Bullet list" onclick="wrapLine('• ')"><i class="fas fa-list-ul"></i></button>
                            <div class="toolbar-sep"></div>
                            <button type="button" title="Insert horizontal rule" onclick="insertText('\n---\n')"><i class="fas fa-minus"></i></button>
                            <button type="button" title="Clear formatting" onclick="clearFormatting()"><i class="fas fa-remove-format"></i></button>
                        </div>

                        <textarea id="pagedes" name="pagedes"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                  rows="14" required><?php echo htmlspecialchars($pageDes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div id="descCounter" class="char-counter">0 / 10000</div>
                    </div>

                    <!-- Actions row -->
                    <div class="flex items-center justify-between pt-2">
                        <!-- Last saved indicator -->
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-database mr-1"></i>
                            Changes are saved to the database on submit.
                        </p>
                        <div class="flex gap-3">
                            <button type="reset"
                                    class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                Reset
                            </button>
                            <button type="submit" name="submit"
                                    class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all text-sm font-medium flex items-center gap-2">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Info card -->
            <div class="mt-6 bg-white rounded-lg shadow p-4 flex items-start gap-3 text-sm text-gray-600">
                <i class="fas fa-info-circle text-blue-400 mt-0.5 flex-shrink-0"></i>
                <div>
                    <p class="font-medium text-gray-700 mb-1">About this Page</p>
                    <p>The content saved here is displayed on the public-facing About Us page of BizTracker.
                       The title appears as the page heading, and the description is rendered as the main body content.
                       Use the formatting toolbar for basic markup such as headings, bold, and bullet points.</p>
                </div>
            </div>

        </main>
    </div><!-- end .main-content -->

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        /* ---------- Sidebar toggle ---------- */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('active');
                });
            }
        });

        function confirmLogout() {
            return confirm('Are you sure you want to log out?');
        }
    </script>

    <script>
        /* ---------- Character counters ---------- */
        function attachCounter(inputId, counterId, maxLen) {
            const input   = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            if (!input || !counter) return;
            function update() {
                const len = input.value.length;
                counter.textContent = len.toLocaleString() + ' / ' + maxLen.toLocaleString();
                counter.className = 'char-counter';
                if (len >= maxLen)             counter.classList.add('over');
                else if (len >= maxLen * 0.9)  counter.classList.add('warn');
            }
            input.addEventListener('input', update);
            update();
        }
        attachCounter('pagetitle', 'titleCounter', 255);
        attachCounter('pagedes',   'descCounter',  10000);
    </script>

    <script>
        /* ---------- Lightweight toolbar helpers ---------- */
        function wrapText(before, after) {
            const ta    = document.getElementById('pagedes');
            const start = ta.selectionStart;
            const end   = ta.selectionEnd;
            const sel   = ta.value.substring(start, end);
            ta.value    = ta.value.substring(0, start) + before + sel + after + ta.value.substring(end);
            ta.selectionStart = start + before.length;
            ta.selectionEnd   = start + before.length + sel.length;
            ta.focus();
            ta.dispatchEvent(new Event('input'));
        }

        function wrapLine(prefix) {
            const ta    = document.getElementById('pagedes');
            const start = ta.selectionStart;
            const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
            ta.value = ta.value.substring(0, lineStart) + prefix + ta.value.substring(lineStart);
            ta.selectionStart = ta.selectionEnd = start + prefix.length;
            ta.focus();
            ta.dispatchEvent(new Event('input'));
        }

        function insertText(text) {
            const ta    = document.getElementById('pagedes');
            const start = ta.selectionStart;
            ta.value    = ta.value.substring(0, start) + text + ta.value.substring(start);
            ta.selectionStart = ta.selectionEnd = start + text.length;
            ta.focus();
            ta.dispatchEvent(new Event('input'));
        }

        function clearFormatting() {
            const ta    = document.getElementById('pagedes');
            const start = ta.selectionStart;
            const end   = ta.selectionEnd;
            if (start === end) return;
            const sel = ta.value.substring(start, end)
                .replace(/\*\*|__|~~|`/g, '')
                .replace(/<\/?[^>]+>/g, '')
                .replace(/^#+\s/gm, '')
                .replace(/^[•\-]\s/gm, '');
            ta.value = ta.value.substring(0, start) + sel + ta.value.substring(end);
            ta.selectionStart = start;
            ta.selectionEnd   = start + sel.length;
            ta.focus();
            ta.dispatchEvent(new Event('input'));
        }
    </script>

    <script>
        /* ---------- Live preview ---------- */
        const previewToggle = document.getElementById('previewToggle');
        const previewPanel  = document.getElementById('previewPanel');
        const titleInput    = document.getElementById('pagetitle');
        const descInput     = document.getElementById('pagedes');
        const previewTitle  = document.getElementById('previewTitle');
        const previewDesc   = document.getElementById('previewDesc');
        let previewOpen = false;

        function updatePreview() {
            previewTitle.textContent = titleInput.value;
            previewDesc.textContent  = descInput.value;
        }

        previewToggle.addEventListener('click', function () {
            previewOpen = !previewOpen;
            previewPanel.classList.toggle('hidden', !previewOpen);
            previewToggle.innerHTML = previewOpen
                ? '<i class="fas fa-eye-slash"></i> Hide Preview'
                : '<i class="fas fa-eye"></i> Preview';
            if (previewOpen) updatePreview();
        });

        titleInput.addEventListener('input', function () { if (previewOpen) updatePreview(); });
        descInput.addEventListener('input',  function () { if (previewOpen) updatePreview(); });

        /* Reset button also resets preview */
        document.querySelector('button[type="reset"]').addEventListener('click', function () {
            setTimeout(function () { if (previewOpen) updatePreview(); }, 0);
        });
    </script>

</body>
</html>