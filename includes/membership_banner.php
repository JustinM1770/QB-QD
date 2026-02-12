<?php
/**
 * Banner de marketing para membresía QuickBite
 * Incluir en páginas principales para promocionar la membresía
 */

// Verificar si el usuario tiene membresía activa
$mostrar_banner_membresia = true;

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Verificar si ya es miembro
    if (!isset($db)) {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
    }

    if (!class_exists('Membership')) {
        require_once __DIR__ . '/../models/Membership.php';
    }

    try {
        $membership_check = new Membership($db);
        $membership_check->id_usuario = $_SESSION["id_usuario"];
        if ($membership_check->isActive()) {
            $mostrar_banner_membresia = false;
        }
    } catch (Exception $e) {
        // En caso de error, mostrar el banner
    }
}

// También verificar si el usuario ha cerrado el banner recientemente (cookie)
if (isset($_COOKIE['hide_membership_banner'])) {
    $mostrar_banner_membresia = false;
}

if ($mostrar_banner_membresia):
?>
<!-- Banner Membresía QuickBite -->
<div id="membership-promo-banner" style="
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 12px 16px;
    margin: 0 -15px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    position: relative;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
">
    <div style="display: flex; align-items: center; gap: 12px;">
        <div style="
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        ">
            <i class="fas fa-crown" style="color: #ffd700; font-size: 18px;"></i>
        </div>
        <div>
            <div style="color: white; font-weight: 600; font-size: 14px;">
                Membresía QuickBite <span style="background: #ffd700; color: #333; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 6px;">5% OFF</span>
            </div>
            <div style="color: rgba(255,255,255,0.9); font-size: 12px;">
                Solo $65/mes - Envío gratis + 5% en tu primer pedido
            </div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="membership_subscribe.php" style="
            background: white;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: transform 0.2s;
            white-space: nowrap;
        " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            Suscribirme
        </a>
        <button onclick="closeMembershipBanner()" style="
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            padding: 5px;
            font-size: 16px;
        " title="Cerrar">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
<script>
function closeMembershipBanner() {
    document.getElementById('membership-promo-banner').style.display = 'none';
    // Guardar cookie por 24 horas
    document.cookie = 'hide_membership_banner=1; max-age=86400; path=/';
}
</script>
<?php endif; ?>
