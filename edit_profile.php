<?php
require_once __DIR__ . '/config/db.php';
requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Always fetch fresh user (bypass currentUser() static cache)
$fetchMe = function () use ($db, $userId): array {
    $s = $db->prepare('SELECT * FROM users WHERE id = ?');
    $s->execute([$userId]);
    return $s->fetch() ?: [];
};
$me = $fetchMe();

// Session flash (Post/Redirect/Get)
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ── Constants ──────────────────────────────────────────────────────────────
define('AVATAR_DIR',       __DIR__ . '/uploads/avatars/');
define('AVATAR_URL_BASE',  '/uploads/avatars/');
define('MAX_AVATAR_BYTES', 5 * 1024 * 1024); // 5 MB
define('MAX_BIO_LEN',      300);

// ── Helpers ────────────────────────────────────────────────────────────────
function epSafe(string $s, int $max = 255): string {
    return substr(trim(strip_tags($s)), 0, $max);
}

function epRateOk(?string $updatedAt, int $secs): bool {
    if (!$updatedAt) return true;
    return (time() - (int)strtotime($updatedAt)) >= $secs;
}

// ── POST handling ──────────────────────────────────────────────────────────
$errors    = [];
$activeTab = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Basic profile + social links ──────────────────────────────────────
    if ($action === 'profile') {
        if (!epRateOk($me['updated_at'] ?? null, 30)) {
            $errors[] = 'Espera al menos 30 segundos entre actualizaciones.';
        } else {
            $fullName = epSafe($_POST['full_name'] ?? '', 100);
            $username = epSafe($_POST['username'] ?? '', 50);
            $bio      = epSafe($_POST['bio'] ?? '', MAX_BIO_LEN);

            if (mb_strlen($fullName) < 2) {
                $errors[] = 'El nombre debe tener al menos 2 caracteres.';
            }
            if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
                $errors[] = 'El usuario solo puede contener letras, números y _ (3-50 caracteres).';
            }
            if (empty($errors)) {
                $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
                $chk->execute([$username, $userId]);
                if ($chk->fetch()) {
                    $errors[] = 'Ese nombre de usuario ya está en uso.';
                }
            }

            $social = [];
            foreach (['twitter', 'instagram', 'tiktok', 'facebook', 'website'] as $net) {
                $val = epSafe($_POST['social_' . $net] ?? '', 150);
                if ($val !== '') $social[$net] = $val;
            }

            if (empty($errors)) {
                $db->prepare('
                    UPDATE users
                    SET full_name    = ?,
                        username     = ?,
                        bio          = ?,
                        social_links = ?,
                        updated_at   = NOW()
                    WHERE id = ?
                ')->execute([
                    $fullName, $username, $bio,
                    $social ? json_encode($social, JSON_UNESCAPED_UNICODE) : null,
                    $userId,
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'tab' => 'info',
                                      'msg'  => 'Perfil actualizado correctamente.'];
                header('Location: ' . BASE . '/edit_profile.php');
                exit;
            }
        }
    }

    // ── Avatar upload ─────────────────────────────────────────────────────
    elseif ($action === 'avatar') {
        $file = $_FILES['avatar'] ?? null;
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite permitido.',
            UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió. Inténtalo de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Error interno: carpeta temporal no disponible.',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco.',
        ];

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Por favor selecciona una imagen.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $uploadErrors[$file['error']] ?? 'Error al subir la imagen.';
        } elseif ($file['size'] > MAX_AVATAR_BYTES) {
            $errors[] = 'La imagen no puede superar 5 MB.';
        } else {
            $imgType = @exif_imagetype($file['tmp_name']);
            $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

            if (!$imgType || !in_array($imgType, $allowed, true)) {
                $errors[] = 'Formato no válido. Se admiten JPG, PNG, GIF y WebP.';
            } else {
                $src = match ($imgType) {
                    IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
                    IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
                    IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
                    IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
                    default        => false,
                };

                if (!$src) {
                    $errors[] = 'No se pudo procesar la imagen.';
                } else {
                    [$srcW, $srcH] = getimagesize($file['tmp_name']);
                    $maxDim = 400;
                    $ratio  = min($maxDim / max($srcW, 1), $maxDim / max($srcH, 1), 1.0);
                    $dstW   = max(1, (int)round($srcW * $ratio));
                    $dstH   = max(1, (int)round($srcH * $ratio));

                    $dst   = imagecreatetruecolor($dstW, $dstH);
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefill($dst, 0, 0, $white);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                    imagedestroy($src);

                    if (!is_dir(AVATAR_DIR)) {
                        mkdir(AVATAR_DIR, 0755, true);
                    }

                    $filename = $userId . '_' . bin2hex(random_bytes(8)) . '.jpg';
                    $savePath = AVATAR_DIR . $filename;

                    if (!imagejpeg($dst, $savePath, 85)) {
                        $errors[] = 'No se pudo guardar la imagen en el servidor.';
                    } else {
                        // Delete old local avatar file
                        $old = $me['avatar'] ?? '';
                        if ($old && str_starts_with($old, AVATAR_URL_BASE)) {
                            $oldFile = AVATAR_DIR . basename($old);
                            if (is_file($oldFile)) @unlink($oldFile);
                        }
                        $db->prepare('UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?')
                           ->execute([AVATAR_URL_BASE . $filename, $userId]);
                        $_SESSION['flash'] = ['type' => 'success', 'tab' => 'info',
                                              'msg'  => 'Foto de perfil actualizada.'];
                        header('Location: ' . BASE . '/edit_profile.php');
                        exit;
                    }
                    imagedestroy($dst);
                }
            }
        }
    }

    // ── Delete avatar ─────────────────────────────────────────────────────
    elseif ($action === 'delete_avatar') {
        $old = $me['avatar'] ?? '';
        if ($old && str_starts_with($old, AVATAR_URL_BASE)) {
            $oldFile = AVATAR_DIR . basename($old);
            if (is_file($oldFile)) @unlink($oldFile);
        }
        $db->prepare('UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = ?')
           ->execute([$userId]);
        $_SESSION['flash'] = ['type' => 'success', 'tab' => 'info',
                              'msg'  => 'Foto de perfil eliminada.'];
        header('Location: ' . BASE . '/edit_profile.php');
        exit;
    }

    // ── Change email ──────────────────────────────────────────────────────
    elseif ($action === 'email') {
        $activeTab  = 'security';
        $currentPwd = $_POST['current_password'] ?? '';
        $newEmail   = filter_var(trim($_POST['new_email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if (!password_verify($currentPwd, $me['password_hash'])) {
            $errors[] = 'La contraseña actual no es correcta.';
        } elseif (!$newEmail) {
            $errors[] = 'El email introducido no es válido.';
        } elseif (strtolower($newEmail) === strtolower($me['email'])) {
            $errors[] = 'El nuevo email es igual al actual.';
        } elseif (!epRateOk($me['updated_at'] ?? null, 300)) {
            $errors[] = 'Por seguridad, espera 5 minutos entre cambios de datos sensibles.';
        } else {
            $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $chk->execute([$newEmail, $userId]);
            if ($chk->fetch()) {
                $errors[] = 'Ese email ya está registrado por otro usuario.';
            } else {
                $db->prepare('UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?')
                   ->execute([$newEmail, $userId]);
                $_SESSION['flash'] = ['type' => 'success', 'tab' => 'security',
                                      'msg'  => 'Email actualizado a ' . htmlspecialchars($newEmail) . '.'];
                header('Location: ' . BASE . '/edit_profile.php');
                exit;
            }
        }
    }

    // ── Change password ───────────────────────────────────────────────────
    elseif ($action === 'password') {
        $activeTab  = 'security';
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPwd, $me['password_hash'])) {
            $errors[] = 'La contraseña actual no es correcta.';
        } elseif (mb_strlen($newPwd) < 8) {
            $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($newPwd !== $confirmPwd) {
            $errors[] = 'Las contraseñas no coinciden.';
        } elseif (password_verify($newPwd, $me['password_hash'])) {
            $errors[] = 'La nueva contraseña no puede ser igual a la actual.';
        } elseif (!epRateOk($me['updated_at'] ?? null, 300)) {
            $errors[] = 'Por seguridad, espera 5 minutos entre cambios de datos sensibles.';
        } else {
            $db->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
               ->execute([password_hash($newPwd, PASSWORD_DEFAULT), $userId]);
            $_SESSION['flash'] = ['type' => 'success', 'tab' => 'security',
                                  'msg'  => 'Contraseña cambiada correctamente.'];
            header('Location: ' . BASE . '/edit_profile.php');
            exit;
        }
    }
}

// Parse social_links JSON
$socialLinks = [];
if (!empty($me['social_links'])) {
    $decoded = json_decode($me['social_links'], true);
    if (is_array($decoded)) $socialLinks = $decoded;
}

// Active tab from flash
if ($flash && !empty($flash['tab'])) {
    $activeTab = $flash['tab'];
}

$pageTitle  = 'Editar perfil';
$activePage = 'profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-content ep-page">

  <!-- Page header -->
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div>
      <h1>Editar perfil</h1>
      <p>Personaliza cómo te ven otros usuarios de CityLive</p>
    </div>
    <a href="<?= BASE ?>/profile.php" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Ver mi perfil
    </a>
  </div>

  <!-- Flash -->
  <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px;">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- Validation errors -->
  <?php if ($errors): ?>
    <div class="flash flash-error" style="margin-bottom:20px;">
      <div style="display:flex;flex-direction:column;gap:3px;">
        <?php foreach ($errors as $err): ?>
          <div><i class="fa-solid fa-circle-exclamation" style="color:var(--red);margin-right:6px;"></i><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="ep-layout">

    <!-- ══ AVATAR SIDEBAR ═══════════════════════════════════════════ -->
    <aside class="ep-sidebar">
      <div class="card ep-avatar-card">

        <!-- Preview circle -->
        <div class="ep-av-wrap" id="ep-av-wrap">
          <?php if ($me['avatar']): ?>
            <img id="ep-av-img" src="<?= htmlspecialchars($me['avatar']) ?>?v=<?= time() ?>" alt="Avatar">
          <?php else: ?>
            <span class="ep-av-letter" id="ep-av-letter">
              <?= strtoupper(substr($me['full_name'] ?? $me['username'], 0, 1)) ?>
            </span>
          <?php endif; ?>
          <label for="ep-file-input" class="ep-av-cam" title="Cambiar foto de perfil">
            <i class="fa-solid fa-camera"></i>
          </label>
        </div>

        <!-- Hidden form for avatar upload -->
        <form method="POST" enctype="multipart/form-data" id="ep-avatar-form">
          <input type="hidden" name="action" value="avatar">
          <input type="file" id="ep-file-input" name="avatar"
                 accept="image/jpeg,image/png,image/gif,image/webp"
                 style="display:none">
        </form>

        <!-- Avatar action buttons -->
        <div class="ep-av-btns">
          <button type="button" class="btn btn-outline btn-sm btn-block"
                  onclick="document.getElementById('ep-file-input').click()">
            <i class="fa-solid fa-upload"></i> Cambiar foto
          </button>
          <?php if ($me['avatar']): ?>
          <form method="POST" style="margin-top:6px;">
            <input type="hidden" name="action" value="delete_avatar">
            <button type="submit" class="btn btn-danger btn-sm btn-block"
                    onclick="return confirm('¿Eliminar la foto de perfil?')">
              <i class="fa-solid fa-trash"></i> Eliminar foto
            </button>
          </form>
          <?php endif; ?>
        </div>

        <!-- User mini info -->
        <div class="ep-av-info">
          <div class="ep-av-name" id="ep-name-preview">
            <?= htmlspecialchars($me['full_name'] ?? $me['username']) ?>
          </div>
          <div class="ep-av-handle">@<span id="ep-handle-preview"><?= htmlspecialchars($me['username']) ?></span></div>
          <p class="ep-av-hint">
            JPG · PNG · GIF · WebP<br>
            Máx. 5 MB · Redimensionado a 400 × 400 px
          </p>
        </div>

      </div>
    </aside>

    <!-- ══ MAIN FORMS ════════════════════════════════════════════════ -->
    <div class="ep-main">

      <!-- Tab navigation -->
      <div class="ep-tabs">
        <button class="ep-tab <?= $activeTab === 'info' ? 'active' : '' ?>"
                onclick="epShowTab('info')" id="ep-btn-info">
          <i class="fa-solid fa-user"></i> Información
        </button>
        <button class="ep-tab <?= $activeTab === 'security' ? 'active' : '' ?>"
                onclick="epShowTab('security')" id="ep-btn-security">
          <i class="fa-solid fa-shield-halved"></i> Seguridad
        </button>
      </div>

      <!-- ══ TAB: Información ══════════════════════════════════════ -->
      <div id="ep-tab-info" <?= $activeTab !== 'info' ? 'style="display:none"' : '' ?>>

        <form method="POST" id="ep-profile-form">
          <input type="hidden" name="action" value="profile">

          <!-- Información básica -->
          <div class="card ep-section">
            <h3 class="ep-section-title">
              <i class="fa-solid fa-id-card"></i> Información básica
            </h3>

            <div class="ep-two-col">
              <div class="form-group">
                <label class="form-label">Nombre visible</label>
                <input type="text" name="full_name" class="form-input"
                       value="<?= htmlspecialchars($me['full_name'] ?? '') ?>"
                       maxlength="100" placeholder="Tu nombre completo" required
                       oninput="document.getElementById('ep-name-preview').textContent = this.value || '—'">
              </div>
              <div class="form-group">
                <label class="form-label">Nombre de usuario</label>
                <div class="ep-prefix-wrap">
                  <span class="ep-prefix">@</span>
                  <input type="text" name="username" class="form-input ep-prefixed"
                         value="<?= htmlspecialchars($me['username']) ?>"
                         maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" required
                         placeholder="tu_usuario"
                         oninput="document.getElementById('ep-handle-preview').textContent = this.value">
                </div>
                <div class="form-helper">Solo letras, números y _ · 3-50 caracteres</div>
              </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                <span>Biografía</span>
                <span id="ep-bio-count" style="font-weight:400;color:var(--text3);font-size:11px;">
                  <?= mb_strlen($me['bio'] ?? '') ?>/300
                </span>
              </label>
              <textarea name="bio" id="ep-bio" class="form-textarea"
                        maxlength="300" rows="3"
                        placeholder="Cuéntanos algo sobre ti..."><?= htmlspecialchars($me['bio'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Redes sociales -->
          <div class="card ep-section">
            <h3 class="ep-section-title">
              <i class="fa-solid fa-share-nodes"></i> Redes sociales
              <span class="ep-badge-opt">Opcional</span>
            </h3>

            <div class="ep-social-grid">
              <div class="form-group">
                <label class="form-label">
                  <i class="fa-brands fa-x-twitter"></i> X / Twitter
                </label>
                <div class="ep-prefix-wrap">
                  <span class="ep-prefix">@</span>
                  <input type="text" name="social_twitter" class="form-input ep-prefixed"
                         value="<?= htmlspecialchars($socialLinks['twitter'] ?? '') ?>"
                         maxlength="50" placeholder="usuario">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">
                  <i class="fa-brands fa-instagram"></i> Instagram
                </label>
                <div class="ep-prefix-wrap">
                  <span class="ep-prefix">@</span>
                  <input type="text" name="social_instagram" class="form-input ep-prefixed"
                         value="<?= htmlspecialchars($socialLinks['instagram'] ?? '') ?>"
                         maxlength="50" placeholder="usuario">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">
                  <i class="fa-brands fa-tiktok"></i> TikTok
                </label>
                <div class="ep-prefix-wrap">
                  <span class="ep-prefix">@</span>
                  <input type="text" name="social_tiktok" class="form-input ep-prefixed"
                         value="<?= htmlspecialchars($socialLinks['tiktok'] ?? '') ?>"
                         maxlength="50" placeholder="usuario">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">
                  <i class="fa-brands fa-facebook"></i> Facebook
                </label>
                <input type="text" name="social_facebook" class="form-input"
                       value="<?= htmlspecialchars($socialLinks['facebook'] ?? '') ?>"
                       maxlength="100" placeholder="nombre o URL">
              </div>
              <div class="form-group ep-span2">
                <label class="form-label">
                  <i class="fa-solid fa-globe"></i> Sitio web
                </label>
                <input type="url" name="social_website" class="form-input"
                       value="<?= htmlspecialchars($socialLinks['website'] ?? '') ?>"
                       maxlength="150" placeholder="https://tusitio.com">
              </div>
            </div>
          </div>

          <div style="margin-top:16px;">
            <button type="submit" class="btn btn-primary" id="ep-save-btn">
              <i class="fa-solid fa-floppy-disk"></i>
              <span id="ep-save-txt">Guardar cambios</span>
            </button>
          </div>
        </form>
      </div>
      <!-- /info tab -->

      <!-- ══ TAB: Seguridad ════════════════════════════════════════ -->
      <div id="ep-tab-security" <?= $activeTab !== 'security' ? 'style="display:none"' : '' ?>>

        <!-- Change email -->
        <div class="card ep-section">
          <h3 class="ep-section-title">
            <i class="fa-solid fa-envelope"></i> Cambiar email
          </h3>
          <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">
            Email actual: <strong><?= htmlspecialchars($me['email']) ?></strong>
          </p>
          <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="email">
            <div class="ep-two-col">
              <div class="form-group">
                <label class="form-label">Nuevo email</label>
                <input type="email" name="new_email" class="form-input"
                       placeholder="nuevo@email.com" maxlength="100" required
                       autocomplete="off">
              </div>
              <div class="form-group">
                <label class="form-label">Contraseña actual <span class="ep-req">*</span></label>
                <input type="password" name="current_password" class="form-input"
                       placeholder="••••••••" required
                       autocomplete="current-password">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-envelope"></i> Cambiar email
            </button>
          </form>
        </div>

        <!-- Change password -->
        <div class="card ep-section">
          <h3 class="ep-section-title">
            <i class="fa-solid fa-key"></i> Cambiar contraseña
          </h3>
          <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="password">
            <div class="form-group">
              <label class="form-label">Contraseña actual <span class="ep-req">*</span></label>
              <input type="password" name="current_password" class="form-input"
                     style="max-width:320px;"
                     placeholder="••••••••" required
                     autocomplete="current-password">
            </div>
            <div class="ep-two-col">
              <div class="form-group">
                <label class="form-label">Nueva contraseña</label>
                <input type="password" name="new_password" id="ep-new-pwd" class="form-input"
                       placeholder="Mínimo 8 caracteres" minlength="8" required
                       autocomplete="new-password"
                       oninput="epPwdStrength(this.value)">
                <div class="ep-strength-bar" id="ep-strength-wrap" style="display:none;">
                  <div class="ep-strength-fill" id="ep-strength-fill"></div>
                </div>
                <div class="form-helper" id="ep-strength-lbl"></div>
              </div>
              <div class="form-group">
                <label class="form-label">Repetir nueva contraseña</label>
                <input type="password" name="confirm_password" id="ep-confirm-pwd" class="form-input"
                       placeholder="Repite la contraseña" required
                       autocomplete="new-password"
                       oninput="epPwdMatch()">
                <div class="form-helper" id="ep-match-lbl"></div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-key"></i> Cambiar contraseña
            </button>
          </form>
        </div>

      </div>
      <!-- /security tab -->

    </div>
    <!-- /ep-main -->
  </div>
  <!-- /ep-layout -->
</div>

<script>
// ── Tab switching ────────────────────────────────────────────────────────
function epShowTab(tab) {
  ['info','security'].forEach(function(t) {
    document.getElementById('ep-tab-' + t).style.display  = (t === tab) ? '' : 'none';
    document.getElementById('ep-btn-' + t).classList.toggle('active', t === tab);
  });
}

// ── Avatar live preview + auto-submit ────────────────────────────────────
document.getElementById('ep-file-input').addEventListener('change', function () {
  if (!this.files || !this.files[0]) return;
  var file = this.files[0];
  if (file.size > 5 * 1024 * 1024) {
    alert('La imagen supera 5 MB. Por favor elige una más pequeña.');
    this.value = '';
    return;
  }
  var reader = new FileReader();
  reader.onload = function (e) {
    var wrap = document.getElementById('ep-av-wrap');
    var old  = wrap.querySelector('img') || wrap.querySelector('.ep-av-letter');
    if (old) old.remove();
    var img = document.createElement('img');
    img.id  = 'ep-av-img';
    img.src = e.target.result;
    wrap.insertBefore(img, wrap.querySelector('.ep-av-cam'));
    // Show loading state then submit
    setTimeout(function () { document.getElementById('ep-avatar-form').submit(); }, 150);
  };
  reader.readAsDataURL(file);
});

// ── Bio character counter ────────────────────────────────────────────────
(function () {
  var bio   = document.getElementById('ep-bio');
  var count = document.getElementById('ep-bio-count');
  if (!bio || !count) return;
  bio.addEventListener('input', function () {
    var len = [...this.value].length;
    count.textContent = len + '/300';
    count.style.color = len > 280 ? 'var(--red)' : 'var(--text3)';
  });
})();

// ── Password strength indicator ──────────────────────────────────────────
function epPwdStrength(pwd) {
  var wrap = document.getElementById('ep-strength-wrap');
  var fill = document.getElementById('ep-strength-fill');
  var lbl  = document.getElementById('ep-strength-lbl');
  if (!pwd) { wrap.style.display = 'none'; lbl.textContent = ''; return; }
  wrap.style.display = '';
  var score = 0;
  if (pwd.length >= 8)             score++;
  if (pwd.length >= 12)            score++;
  if (/[A-Z]/.test(pwd))           score++;
  if (/[0-9]/.test(pwd))           score++;
  if (/[^A-Za-z0-9]/.test(pwd))   score++;
  var levels = [
    {w:'20%', c:'var(--red)',     t:'Muy débil'},
    {w:'40%', c:'var(--orange)',  t:'Débil'},
    {w:'60%', c:'var(--yellow)',  t:'Moderada'},
    {w:'80%', c:'var(--primary)', t:'Buena'},
    {w:'100%',c:'var(--green)',   t:'Muy fuerte'},
  ];
  var lvl = levels[Math.min(score - 1, 4)] || levels[0];
  fill.style.width      = lvl.w;
  fill.style.background = lvl.c;
  lbl.textContent       = 'Seguridad: ' + lvl.t;
  lbl.style.color       = lvl.c;
}

// ── Password match indicator ─────────────────────────────────────────────
function epPwdMatch() {
  var lbl = document.getElementById('ep-match-lbl');
  var a   = document.getElementById('ep-new-pwd').value;
  var b   = document.getElementById('ep-confirm-pwd').value;
  if (!b) { lbl.textContent = ''; return; }
  if (a === b) {
    lbl.textContent = 'Las contraseñas coinciden';
    lbl.style.color = 'var(--green)';
  } else {
    lbl.textContent = 'No coinciden todavía';
    lbl.style.color = 'var(--red)';
  }
}

// ── Save button loading state ────────────────────────────────────────────
document.getElementById('ep-profile-form').addEventListener('submit', function () {
  var btn = document.getElementById('ep-save-btn');
  var txt = document.getElementById('ep-save-txt');
  btn.disabled    = true;
  txt.textContent = 'Guardando...';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
