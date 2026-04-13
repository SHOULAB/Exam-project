<?php
session_start();
require_once('../../assets/database.php');

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['administrator', 'moderator'])) {
    header("Location: ../../user/php/login.php");
    exit();
}

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
                $error = 'Jūs nevarat deāktivēt savu kontu!';
            } else {
                $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET is_active = 0 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $user_id);

                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Lietotājs veiksmīgi deāktivēts!';
                } else {
                    $error = 'Kļūda deāktivējot lietotāju!';
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($action === 'activate') {
            $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET is_active = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Lietotājs veiksmīgi aktivēts!';
            } else {
                $error = 'Kļūda aktivējot lietotāju!';
            }
            mysqli_stmt_close($stmt);
        }

        if ($action === 'edit') {
            $new_username = trim($_POST['username'] ?? '');
            $new_email    = trim($_POST['email'] ?? '');
            $new_role     = $_POST['role'] ?? 'user';
            $allowed_roles = ['user', 'moderator', 'administrator'];

            if (empty($new_username) || empty($new_email)) {
                $error = 'Lietotājvārds un e-pasts nedrīkst būt tukši!';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Nederīgs e-pasta formāts!';
            } elseif (!in_array($new_role, $allowed_roles)) {
                $error = 'Nederīga loma!';
            } else {
                // Check email uniqueness (exclude current user)
                $chk = mysqli_prepare($savienojums, "SELECT id FROM BU_users WHERE email = ? AND id != ?");
                mysqli_stmt_bind_param($chk, "si", $new_email, $user_id);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);
                $email_taken = mysqli_stmt_num_rows($chk) > 0;
                mysqli_stmt_close($chk);

                if ($email_taken) {
                    $error = 'Šis e-pasts jau tiek izmantots!';
                } else {
                    $stmt = mysqli_prepare($savienojums, "UPDATE BU_users SET username = ?, email = ?, role = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssi", $new_username, $new_email, $new_role, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = 'Lietotāja dati veiksmīgi atjaunināti!';
                    } else {
                        $error = 'Kļūda atjauninot lietotāja datus!';
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
                $success = $new_role === 'administrator' ? 'Lietotājs veiksmīgi iecelts par administratoru!' : 'Administratora tiesības veiksmīgi atsauktas!';
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

// search bar
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT id, username, email, role, created_at, last_login, is_active FROM BU_users WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY FIELD(role, 'administrator', 'moderator', 'user'), created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($savienojums, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($savienojums, $query);
}

$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

$total_users = count($users);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lietotāju pārvaldība - Budgetar</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../../assets/image/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php $active_page = 'users'; include 'sidebar.php'; ?>

        <main class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">Lietotāju pārvaldība</h1>
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
                            placeholder="Meklēt lietotājus..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </form>
                </div>
            </div>

            <div class="users-table-container">
                <div class="table-header">
                    <h2 class="table-title">Lietotāji</h2>
                    <span class="table-count"><?php echo $total_users; ?> rezultāti</span>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <div class="empty-text">Nav atrasti lietotāji</div>
                        <div class="empty-subtext">Mēģiniet mainīt meklēšanas kritērijus</div>
                    </div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Lietotājs</th>
                                <th>Reģistrācijas datums</th>
                                <th>Pēdējā pieslēgšanās</th>
                                <th>Darbības</th>
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
                                                    <?php if (!$user['is_active']): ?><span class="badge-deactivated">Deāktivēts</span><?php endif; ?>
                                                    <span class="badge-role badge-role--<?php echo $user['role']; ?>">
                                                        <?php echo match($user['role']) {
                                                            'administrator' => 'Admins',
                                                            'moderator'     => 'Moderators',
                                                            default         => 'Lietotājs',
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
                                                title="Rediģēt"
                                                onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>', '<?php echo htmlspecialchars(addslashes($user['email'])); ?>', '<?php echo $user['role']; ?>')"
                                            >
                                                <i class="fa-solid fa-pencil"></i>
                                            </button>
                                            <?php if ($user['is_active']): ?>
                                            <button class="tbl-btn tbl-btn--delete"
                                                title="Deāktivēt"
                                                onclick="openDeactivateModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>')"
                                            >
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="tbl-btn tbl-btn--activate" title="Aktivēt"
                                                onclick="activateUser(<?php echo $user['id']; ?>)">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../js/script.js"></script>

    <!-- Toast notification -->
    <div id="admToast" class="adm-toast"></div>

    <!-- Edit user modal -->
    <div id="editModal" class="adm-modal" style="display:none;">
        <div class="adm-modal-box adm-modal-box--wide">
            <div class="adm-modal-header">
                <h2 class="adm-modal-title">Rediģēt lietotāju</h2>
                <button type="button" class="adm-modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="adm-modal-body">
                    <div class="form-group">
                        <label class="form-label" for="editUsername">Lietotājvārds</label>
                        <input type="text" id="editUsername" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editEmail">E-pasts</label>
                        <input type="email" id="editEmail" name="email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editRole">Loma</label>
                        <input type="hidden" id="editRole" name="role" value="user">
                        <div class="custom-select" id="editRoleSelect">
                            <div class="custom-select-trigger">
                                <span class="custom-select-value" id="editRoleValue">Lietotājs</span>
                                <i class="fa-solid fa-chevron-down custom-select-arrow"></i>
                            </div>
                            <ul class="custom-options" id="editRoleOptions">
                                <li class="custom-option" data-value="user"><i class="fa-solid fa-user"></i> Lietotājs</li>
                                <li class="custom-option" data-value="moderator"><i class="fa-solid fa-shield"></i> Moderators</li>
                                <li class="custom-option" data-value="administrator"><i class="fa-solid fa-shield-halved"></i> Administrators</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="adm-modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Atcelt</button>
                    <button type="submit" class="btn btn-primary">Saglabāt</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deactivate confirmation modal -->
    <div id="deactivateModal" class="adm-modal" style="display:none;">
        <div class="adm-modal-box">
            <div class="adm-modal-icon"><i class="fa-solid fa-ban"></i></div>
            <h2 class="adm-modal-title">Deāktivēt kontu?</h2>
            <p class="adm-modal-desc">Lietotājs <strong id="deactivateUsername"></strong> nevarēs piekļūst savam kontam, līdz tas tiks aktivēts atkārtoti.</p>
            <div class="adm-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()">Atcelt</button>
                <form method="POST" id="deactivateForm" style="display:inline;">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="user_id" id="deactivateUserId">
                    <button type="submit" class="btn btn-danger">Deāktivēt</button>
                </form>
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
                badge.textContent = 'Deāktivēts';
                nameSpan.insertBefore(badge, nameSpan.querySelector('.badge-role'));
            }
            var banBtn = row.querySelector('.tbl-btn--delete');
            if (banBtn) {
                var activateBtn = document.createElement('button');
                activateBtn.type = 'button';
                activateBtn.className = 'tbl-btn tbl-btn--activate';
                activateBtn.title = 'Aktivēt';
                activateBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                activateBtn.onclick = (function(id) { return function() { activateUser(id); }; })(userId);
                banBtn.replaceWith(activateBtn);
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
            var activateBtn = row.querySelector('.tbl-btn--activate');
            if (activateBtn) {
                var username = row.dataset.username;
                var banBtn = document.createElement('button');
                banBtn.type = 'button';
                banBtn.className = 'tbl-btn tbl-btn--delete';
                banBtn.title = 'Deāktivēt';
                banBtn.innerHTML = '<i class="fa-solid fa-ban"></i>';
                banBtn.onclick = (function(id, uname) { return function() { openDeactivateModal(id, uname); }; })(userId, username);
                activateBtn.replaceWith(banBtn);
            }
        });
    }

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
            roleBadge.textContent = role === 'administrator' ? 'Admins' : role === 'moderator' ? 'Moderators' : 'Lietotājs';
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
        user:          '<i class="fa-solid fa-user"></i> Lietotājs',
        moderator:     '<i class="fa-solid fa-shield"></i> Moderators',
        administrator: '<i class="fa-solid fa-shield-halved"></i> Administrators'
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