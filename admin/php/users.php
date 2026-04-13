<?php
session_start();
require_once('../../assets/database.php');

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['administrator', 'moderator'])) {
    header("Location: ../../user/php/login.php");
    exit();
}

// ── Load language + translations ──────────────────────────────────────────────
$_lang = $_SESSION['language'] ?? 'lv';
$_traw = json_decode(file_get_contents(__DIR__ . '/translate.json'), true) ?? [];
$_t    = $_traw[$_lang] ?? $_traw['lv'] ?? [];

$success = '';
$error = '';
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// user edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];

        if ($action === 'deactivate') {
            if (isset($_SESSION['user_id']) && $user_id == $_SESSION['user_id']) {
                $error = $_t['users.err.self.deactivate'] ?? 'Jūs nevarat deāktivēt savu kontu!';
            } else {
                $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET is_active = 0 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $user_id);

                if (mysqli_stmt_execute($stmt)) {
                    $success = $_t['users.msg.deactivated'] ?? 'Lietotājs veiksmīgi deāktivēts!';
                } else {
                    $error = $_t['users.err.self.deactivate'] ?? 'Kļūda deāktivējot lietotāju!';
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($action === 'activate') {
            $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET is_active = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = $_t['users.msg.activated'] ?? 'Lietotājs veiksmīgi aktivēts!';
            } else {
                $error = $_t['users.err.self.deactivate'] ?? 'Kļūda aktivējot lietotāju!';
            }
            mysqli_stmt_close($stmt);
        }

        if ($action === 'edit') {
            $new_username = trim($_POST['username'] ?? '');
            $new_email    = trim($_POST['email'] ?? '');
            $new_role     = $_POST['role'] ?? 'user';
            $allowed_roles = ['user', 'moderator', 'administrator'];

            if (empty($new_username) || empty($new_email)) {
                $error = $_t['users.err.empty.fields'] ?? 'Lietotājvārds un e-pasts nedrīkst būt tukši!';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = $_t['users.err.invalid.email'] ?? 'Nerīdīgs e-pasta formāts!';
            } elseif (!in_array($new_role, $allowed_roles)) {
                $error = $_t['users.err.invalid.role'] ?? 'Nerīdīga loma!';
            } else {
                // Check email uniqueness (exclude current user)
                $chk = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE email = ? AND id != ?");
                mysqli_stmt_bind_param($chk, "si", $new_email, $user_id);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);
                $email_taken = mysqli_stmt_num_rows($chk) > 0;
                mysqli_stmt_close($chk);

                if ($email_taken) {
                    $error = $_t['users.err.email.taken'] ?? 'Šis e-pasts jau tiek izmantots!';
                } else {
                    $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET username = ?, email = ?, role = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssi", $new_username, $new_email, $new_role, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = $_t['users.msg.updated'] ?? 'Lietotāja dati veiksmīgi atjaunināti!';
                    } else {
                        $error = $_t['users.err.empty.fields'] ?? 'Kļūda atjauninot lietotāja datus!';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        if ($action === 'delete') {
            if (isset($_SESSION['user_id']) && $user_id == $_SESSION['user_id']) {
                $error = $_t['users.err.self.delete'] ?? 'Jūs nevarat dzēst savu kontu!';
            } else {
                // Only allow deleting deactivated accounts
                $chk = mysqli_prepare($savienojums, "SELECT is_active FROM BU_users WHERE id = ?");
                mysqli_stmt_bind_param($chk, "i", $user_id);
                mysqli_stmt_execute($chk);
                $chk_res = mysqli_stmt_get_result($chk);
                $chk_row = mysqli_fetch_assoc($chk_res);
                mysqli_stmt_close($chk);

                if (!$chk_row || $chk_row['is_active']) {
                    $error = $_t['users.err.active.delete'] ?? 'Var dzēst tikai deāktivētus kontus!';
                } else {
                    $stmt = mysqli_prepare($savienojums, "DELETE FROM BU_users WHERE id = ? AND is_active = 0");
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = $_t['users.msg.deleted'] ?? 'Lietotājs veiksmīgi dzēsts!';
                    } else {
                        $error = $_t['users.err.self.delete'] ?? 'Kļūda dzēšot lietotāju!';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }

        if ($action === 'toggle_role') {
            $stmt = mysqli_prepare($savienojums, "SELECT role FROM BU_users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $current_role);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            $new_role = ($current_role === 'administrator') ? 'user' : 'administrator';
            $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET role = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = $new_role === 'administrator'
                    ? ($_t['users.msg.promoted'] ?? 'Lietotājs veiksmīgi iecelts par administratoru!')
                    : ($_t['users.msg.demoted']  ?? 'Administratora tiesības veiksmīgi atsauktas!');
            } else {
                $error = 'Kļūda mainīot lomās!';
            }
            mysqli_stmt_close($stmt);
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => empty($error), 'message' => $success ?: $error]);
            exit();
        }
    }
}

// search bar + pagination
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page     = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));

// COUNT total matching rows
$count_query  = "SELECT COUNT(*) FROM BU_users WHERE 1=1";
$params       = [];
$types        = "";

if (!empty($search)) {
    $count_query .= " AND (username LIKE ? OR email LIKE ?)";
    $search_param  = "%{$search}%";
    $params[]      = $search_param;
    $params[]      = $search_param;
    $types        .= "ss";
}

if (!empty($params)) {
    $stmt = mysqli_prepare($savienojums, $count_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_users);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
} else {
    $total_users = (int)mysqli_fetch_row(mysqli_query($savienojums, $count_query))[0];
}

$total_pages  = max(1, (int)ceil($total_users / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

// Fetch one page of rows
$query  = "SELECT id, username, email, role, created_at, last_login, is_active FROM BU_users WHERE 1=1";
$params = [];
$types  = "";

if (!empty($search)) {
    $query       .= " AND (username LIKE ? OR email LIKE ?)";
    $search_param  = "%{$search}%";
    $params[]      = $search_param;
    $params[]      = $search_param;
    $types        .= "ss";
}

$query .= " ORDER BY FIELD(role, 'administrator', 'moderator', 'user'), created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types   .= "ii";

$stmt = mysqli_prepare($savienojums, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_t['users.page.title'] ?? 'Lietotāju pārvaldība'); ?> - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php $active_page = 'users'; include 'sidebar.php'; ?>

        <main class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title" data-i18n="users.page.title"><?php echo $_t['users.page.title'] ?? 'Lietotāju pārvaldība'; ?></h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 24px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 24px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <form method="GET" action="">
                        <input
                            type="text"
                            name="search"
                            class="search-input"
                            placeholder="<?php echo htmlspecialchars($_t['users.search.placeholder'] ?? 'Meklēt lietotājus...'); ?>"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>
            </div>

            <div class="users-table-container">
                <div class="table-header">
                    <h2 class="table-title" data-i18n="users.table.title"><?php echo $_t['users.table.title'] ?? 'Lietotāji'; ?></h2>
                    <span class="table-count"><?php echo $total_users; ?> <?php echo $_t['users.table.results'] ?? 'rezultāti'; ?></span>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <div class="empty-text" data-i18n="users.empty.text"><?php echo $_t['users.empty.text'] ?? 'Nav atrasti lietotāji'; ?></div>
                        <div class="empty-subtext" data-i18n="users.empty.subtext"><?php echo $_t['users.empty.subtext'] ?? 'Mēġiniet mainīt meklēšanas kritērijus'; ?></div>
                    </div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th data-i18n="users.table.col.user"><?php echo $_t['users.table.col.user'] ?? 'Lietotājs'; ?></th>
                                <th data-i18n="users.table.col.created"><?php echo $_t['users.table.col.created'] ?? 'Reģistrācijas datums'; ?></th>
                                <th data-i18n="users.table.col.last.login"><?php echo $_t['users.table.col.last.login'] ?? 'Pēdējā pieslēgšanās'; ?></th>
                                <th data-i18n="users.table.col.actions"><?php echo $_t['users.table.col.actions'] ?? 'Darbības'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="<?php echo !$user['is_active'] ? 'row-deactivated' : ''; ?>"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div class="user-details">
                                                <span class="user-name">
                                                    <span class="uname-text"><?php echo htmlspecialchars($user['username']); ?></span>
                                                    <?php if (!$user['is_active']): ?><span class="badge-deactivated" data-i18n="users.badge.deactivated"><?php echo $_t['users.badge.deactivated'] ?? 'Deāktivēts'; ?></span><?php endif; ?>
                                                    <span class="badge-role badge-role--<?php echo $user['role']; ?>">
                                                        <?php echo match($user['role']) {
                                                            'administrator' => $_t['users.badge.admin'] ?? 'Admins',
                                                            'moderator'     => $_t['users.badge.moderator'] ?? 'Moderators',
                                                            default         => $_t['users.badge.user'] ?? 'Lietotājs',
                                                        }; ?>
                                                    </span>
                                                </span>
                                                <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="td-muted"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td class="td-muted"><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : '—'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="tbl-btn tbl-btn--edit"
                                                title="<?php echo htmlspecialchars($_t['users.edit.title'] ?? 'Rediģēt'); ?>"
                                                onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>', '<?php echo htmlspecialchars(addslashes($user['email'])); ?>', '<?php echo $user['role']; ?>')"
                                            >
                                                <i class="fa-solid fa-pencil"></i>
                                            </button>
                                            <?php if ($user['is_active']): ?>
                                            <button class="tbl-btn tbl-btn--delete"
                                                title="<?php echo htmlspecialchars($_t['users.deactivate.btn'] ?? 'Deāktivēt'); ?>"
                                                onclick="openDeactivateModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')"
                                            >
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="tbl-btn tbl-btn--activate" title="<?php echo htmlspecialchars($_t['users.activate.title'] ?? 'Aktivēt'); ?>"
                                                onclick="activateUser(<?php echo $user['id']; ?>)">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </button>
                                            <button type="button" class="tbl-btn tbl-btn--delete" title="<?php echo htmlspecialchars($_t['users.delete.btn'] ?? 'Dzēst'); ?>"
                                                onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($total_pages > 1): ?>
                <?php
                    $base_url = '?' . http_build_query(array_filter(['search' => $search]));
                    $sep      = strpos($base_url, '?') !== false && strlen($base_url) > 1 ? '&' : '?';
                    if ($base_url === '?') { $base_url = ''; $sep = '?'; }
                ?>
                <div class="pagination">
                    <a class="pg-btn <?php echo $current_page <= 1 ? 'pg-btn--disabled' : ''; ?>"
                       <?php if ($current_page > 1): ?>href="<?php echo $base_url . $sep; ?>page=<?php echo $current_page - 1; ?>"<?php endif; ?>>
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>

                    <?php
                    $range   = 2;
                    $start   = max(1, $current_page - $range);
                    $end     = min($total_pages, $current_page + $range);
                    if ($start > 1): ?>
                        <a class="pg-btn" href="<?php echo $base_url . $sep; ?>page=1">1</a>
                        <?php if ($start > 2): ?><span class="pg-ellipsis">…</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <a class="pg-btn <?php echo $p === $current_page ? 'pg-btn--active' : ''; ?>"
                           href="<?php echo $base_url . $sep; ?>page=<?php echo $p; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><span class="pg-ellipsis">…</span><?php endif; ?>
                        <a class="pg-btn" href="<?php echo $base_url . $sep; ?>page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <a class="pg-btn <?php echo $current_page >= $total_pages ? 'pg-btn--disabled' : ''; ?>"
                       <?php if ($current_page < $total_pages): ?>href="<?php echo $base_url . $sep; ?>page=<?php echo $current_page + 1; ?>"<?php endif; ?>>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../js/script.js"></script>
    <script>window._i18nData=<?php echo json_encode($_traw); ?>;window._i18nLang=<?php echo json_encode($_lang); ?>;window._i18nIsDefault=false;</script>
    <script src="../../user/js/language.js"></script>

    <!-- Toast notification -->
    <div id="admToast" class="adm-toast"></div>

    <!-- Edit user modal -->
    <div id="editModal" class="adm-modal" style="display:none;">
        <div class="adm-modal-box adm-modal-box--wide">
            <div class="adm-modal-header">
                <h2 class="adm-modal-title" data-i18n="users.edit.title"><?php echo $_t['users.edit.title'] ?? 'Rediģēt lietotāju'; ?></h2>
                <button type="button" class="adm-modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="adm-modal-body">
                    <div class="form-group">
                        <label class="form-label" for="editUsername" data-i18n="users.edit.username.label"><?php echo $_t['users.edit.username.label'] ?? 'Lietotājvārds'; ?></label>
                        <input type="text" id="editUsername" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editEmail" data-i18n="users.edit.email.label"><?php echo $_t['users.edit.email.label'] ?? 'E-pasts'; ?></label>
                        <input type="email" id="editEmail" name="email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editRole" data-i18n="users.edit.role.label"><?php echo $_t['users.edit.role.label'] ?? 'Loma'; ?></label>
                        <input type="hidden" id="editRole" name="role" value="user">
                        <div class="custom-select" id="editRoleSelect">
                            <div class="custom-select-trigger">
                                <span class="custom-select-value" id="editRoleValue"><?php echo $_t['users.badge.user'] ?? 'Lietotājs'; ?></span>
                                <i class="fa-solid fa-chevron-down custom-select-arrow"></i>
                            </div>
                            <ul class="custom-options" id="editRoleOptions">
                                <li class="custom-option" data-value="user"><i class="fa-solid fa-user"></i> <?php echo $_t['users.badge.user'] ?? 'Lietotājs'; ?></li>
                                <li class="custom-option" data-value="moderator"><i class="fa-solid fa-shield"></i> <?php echo $_t['users.badge.moderator'] ?? 'Moderators'; ?></li>
                                <li class="custom-option" data-value="administrator"><i class="fa-solid fa-shield-halved"></i> <?php echo $_t['users.badge.admin'] ?? 'Administrators'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="adm-modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" data-i18n="users.edit.cancel"><?php echo $_t['users.edit.cancel'] ?? 'Atcelt'; ?></button>
                    <button type="submit" class="btn btn-primary" data-i18n="users.edit.save"><?php echo $_t['users.edit.save'] ?? 'Saglabāt'; ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deactivate confirmation modal -->
    <div id="deactivateModal" class="adm-modal" style="display:none;">
        <div class="adm-modal-box">
            <div class="adm-modal-icon"><i class="fa-solid fa-ban"></i></div>
            <h2 class="adm-modal-title" data-i18n="users.deactivate.title"><?php echo $_t['users.deactivate.title'] ?? 'Deāktivēt kontu?'; ?></h2>
            <p class="adm-modal-desc">Lietotājs <strong id="deactivateUsername"></strong> <?php echo $_t['users.deactivate.desc'] ?? 'nevarēs piekļuties savam kontam, līdz tas tiks aktivēts atkārtoti.'; ?></p>
            <div class="adm-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()" data-i18n="users.deactivate.cancel"><?php echo $_t['users.deactivate.cancel'] ?? 'Atcelt'; ?></button>
                <form method="POST" id="deactivateForm" style="display:inline;">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="user_id" id="deactivateUserId">
                    <button type="submit" class="btn btn-danger" data-i18n="users.deactivate.btn"><?php echo $_t['users.deactivate.btn'] ?? 'Deāktivēt'; ?></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div id="deleteModal" class="adm-modal" style="display:none;">
        <div class="adm-modal-box">
            <div class="adm-modal-icon"><i class="fa-solid fa-trash"></i></div>
            <h2 class="adm-modal-title" data-i18n="users.delete.title"><?php echo $_t['users.delete.title'] ?? 'Dzēst kontu?'; ?></h2>
            <p class="adm-modal-desc"><?php echo $_t['users.delete.intro'] ?? 'Vai tiešām vēlies neatgriezeniski dzēst'; ?> <strong id="deleteUsername"></strong><?php echo $_t['users.delete.desc'] ?? '? Visi konta dati tiks izdžēsti un to nebūs iespējams atjaunot.'; ?></p>
            <input type="hidden" id="deleteUserId">
            <div class="adm-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()" data-i18n="users.delete.cancel"><?php echo $_t['users.delete.cancel'] ?? 'Atcelt'; ?></button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()" data-i18n="users.delete.btn"><?php echo $_t['users.delete.btn'] ?? 'Dzēst'; ?></button>
            </div>
        </div>
    </div>

    <script>
    // ── Toast ─────────────────────────────────────────────────────
    function showToast(message, type) {
        var toast = document.getElementById('admToast');
        toast.textContent = message;
        toast.className = 'adm-toast adm-toast--' + (type || 'success') + ' adm-toast--show';
        clearTimeout(toast._t);
        toast._t = setTimeout(function() { toast.classList.remove('adm-toast--show'); }, 3500);
    }

    // ── AJAX sender ─────────────────────────────────────────────
    function sendAction(data, onSuccess) {
        var fd = new FormData();
        Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.success) {
                showToast(json.message, 'success');
                onSuccess(json);
            } else {
                showToast(json.message, 'error');
            }
        })
        .catch(function() { showToast('Savienojuma kļūda!', 'error'); });
    }

    function openDeactivateModal(userId, username) {
        document.getElementById('deactivateUserId').value = userId;
        document.getElementById('deactivateUsername').textContent = username;
        document.getElementById('deactivateModal').style.display = 'flex';
    }
    function closeDeactivateModal() {
        document.getElementById('deactivateModal').style.display = 'none';
    }
    document.getElementById('deactivateModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeactivateModal();
    });

    document.getElementById('deactivateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var userId = document.getElementById('deactivateUserId').value;
        sendAction({ action: 'deactivate', user_id: userId }, function() {
            closeDeactivateModal();
            var row = document.querySelector('tr[data-user-id="' + userId + '"]');
            if (!row) return;
            row.classList.add('row-deactivated');
            var nameSpan = row.querySelector('.user-name');
            if (!nameSpan.querySelector('.badge-deactivated')) {
                var badge = document.createElement('span');
                badge.className = 'badge-deactivated';
                var _d = (window._i18n && window._i18n.T[window._i18n.lang]) || {};
                badge.textContent = _d['users.badge.deactivated'] || 'Deāktivēts';
                nameSpan.insertBefore(badge, nameSpan.querySelector('.badge-role'));
            }
            var banBtn = row.querySelector('.tbl-btn--delete');
            if (banBtn) {
                var activateBtn = document.createElement('button');
                activateBtn.type = 'button';
                activateBtn.className = 'tbl-btn tbl-btn--activate';
                activateBtn.title = (window._i18n && window._i18n.T[window._i18n.lang] && window._i18n.T[window._i18n.lang]['users.activate.title']) || 'Aktivēt';
                activateBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                activateBtn.onclick = (function(id) { return function() { activateUser(id); }; })(userId);
                banBtn.replaceWith(activateBtn);

                var deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'tbl-btn tbl-btn--delete';
                deleteBtn.title = (window._i18n && window._i18n.T[window._i18n.lang] && window._i18n.T[window._i18n.lang]['users.delete.btn']) || 'Dzēst';
                deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                var uname = row.dataset.username;
                deleteBtn.onclick = (function(id, un) { return function() { openDeleteModal(id, un); }; })(userId, uname);
                activateBtn.after(deleteBtn);
            }
        });
    });

    function activateUser(userId) {
        sendAction({ action: 'activate', user_id: userId }, function() {
            var row = document.querySelector('tr[data-user-id="' + userId + '"]');
            if (!row) return;
            row.classList.remove('row-deactivated');
            var badge = row.querySelector('.badge-deactivated');
            if (badge) badge.remove();
            var actionBtns = row.querySelector('.action-buttons');
            // Remove activate + delete buttons
            actionBtns.querySelectorAll('.tbl-btn--activate, .tbl-btn--delete').forEach(function(b) { b.remove(); });
            var username = row.dataset.username;
            var banBtn = document.createElement('button');
            banBtn.type = 'button';
            banBtn.className = 'tbl-btn tbl-btn--delete';
            banBtn.title = (window._i18n && window._i18n.T[window._i18n.lang] && window._i18n.T[window._i18n.lang]['users.deactivate.btn']) || 'Deāktivēt';
            banBtn.innerHTML = '<i class="fa-solid fa-ban"></i>';
            banBtn.onclick = (function(id, uname) { return function() { openDeactivateModal(id, uname); }; })(userId, username);
            actionBtns.appendChild(banBtn);
        });
    }

    function openDeleteModal(userId, username) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUsername').textContent = username;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    function confirmDelete() {
        var userId = document.getElementById('deleteUserId').value;
        sendAction({ action: 'delete', user_id: userId }, function() {
            closeDeleteModal();
            var row = document.querySelector('tr[data-user-id="' + userId + '"]');
            if (row) row.remove();
            // decrement result count
            var countEl = document.querySelector('.table-count');
            if (countEl) {
                var n = parseInt(countEl.textContent) - 1;
                var resultsLabel = (window._i18n && window._i18n.T[window._i18n.lang] && window._i18n.T[window._i18n.lang]['users.table.results']) || 'rezultāti';
                countEl.textContent = n + ' ' + resultsLabel;
            }
        });
    }
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

    function openEditModal(userId, username, email, role) {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUsername').value = username;
        document.getElementById('editEmail').value = email;
        setEditRole(role);
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        if (roleDropdownOpen) closeRoleDropdown();
    }
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var userId   = document.getElementById('editUserId').value;
        var username = document.getElementById('editUsername').value.trim();
        var email    = document.getElementById('editEmail').value.trim();
        var role     = document.getElementById('editRole').value;
        sendAction({ action: 'edit', user_id: userId, username: username, email: email, role: role }, function() {
            closeEditModal();
            var row = document.querySelector('tr[data-user-id="' + userId + '"]');
            if (!row) return;
            row.dataset.username = username;
            row.querySelector('.uname-text').textContent = username;
            row.querySelector('.user-email').textContent = email;
            var roleBadge = row.querySelector('.badge-role');
            roleBadge.className = 'badge-role badge-role--' + role;
            var _d = (window._i18n && window._i18n.T[window._i18n.lang]) || {};
            roleBadge.textContent = role === 'administrator'
                ? (_d['users.badge.admin']     || 'Admins')
                : role === 'moderator'
                    ? (_d['users.badge.moderator'] || 'Moderators')
                    : (_d['users.badge.user']      || 'Lietotājs');
            var editBtn = row.querySelector('.tbl-btn--edit');
            editBtn.setAttribute('onclick', 'openEditModal(' + userId + ', \'' + username.replace(/'/g, "\\'") + '\', \'' + email.replace(/'/g, "\\'") + '\', \'' + role + '\')');
            var banBtn = row.querySelector('.tbl-btn--delete');
            if (banBtn) banBtn.setAttribute('onclick', 'openDeactivateModal(' + userId + ', \'' + username.replace(/'/g, "\\'") + '\')');
        });
    });

    // ── Role custom dropdown ─────────────────────────────────────────────────
    const roleSelect    = document.getElementById('editRoleSelect');
    const roleInput     = document.getElementById('editRole');
    const roleValue     = document.getElementById('editRoleValue');
    const roleOptions   = document.getElementById('editRoleOptions');
    let roleDropdownOpen = false;

    const roleLabels = {
        user:          '<i class="fa-solid fa-user"></i> ' + ((window._i18nData && window._i18nData[window._i18nLang || 'lv']) || {})['users.badge.user']      || 'Lietotājs',
        moderator:     '<i class="fa-solid fa-shield"></i> ' + ((window._i18nData && window._i18nData[window._i18nLang || 'lv']) || {})['users.badge.moderator'] || 'Moderators',
        administrator: '<i class="fa-solid fa-shield-halved"></i> ' + ((window._i18nData && window._i18nData[window._i18nLang || 'lv']) || {})['users.badge.admin'] || 'Administrators'
    };

    function setEditRole(value) {
        roleInput.value = value;
        roleValue.innerHTML = roleLabels[value] || value;
        roleOptions.querySelectorAll('.custom-option').forEach(o => {
            o.classList.toggle('selected', o.dataset.value === value);
        });
    }

    function positionRoleOptions() {
        const rect = roleSelect.getBoundingClientRect();
        roleOptions.style.top    = (rect.bottom + 8) + 'px';
        roleOptions.style.left   = rect.left + 'px';
        roleOptions.style.width  = rect.width + 'px';
    }

    function closeRoleDropdown() {
        roleDropdownOpen = false;
        roleSelect.classList.remove('open');
    }

    roleSelect.querySelector('.custom-select-trigger').addEventListener('click', function(e) {
        e.stopPropagation();
        if (!roleDropdownOpen) positionRoleOptions();
        roleDropdownOpen = !roleDropdownOpen;
        roleSelect.classList.toggle('open', roleDropdownOpen);
    });

    roleOptions.addEventListener('click', function(e) {
        const opt = e.target.closest('.custom-option');
        if (opt) { setEditRole(opt.dataset.value); closeRoleDropdown(); }
    });

    document.addEventListener('click', function(e) {
        if (!roleSelect.contains(e.target)) closeRoleDropdown();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeRoleDropdown(); closeEditModal(); }
    });

    window.addEventListener('resize', function() { if (roleDropdownOpen) positionRoleOptions(); });
    window.addEventListener('scroll', function() { if (roleDropdownOpen) positionRoleOptions(); }, true);
    </script>
</body>
</html>