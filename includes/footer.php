<?php
// school-fees-system/includes/footer.php (CLEANED)
?>
    </div>
    <footer class="main-footer">
        <strong>Copyright &copy; <?= date('Y') ?> <a href="https://exonovaagency.com">ExoNova Agency<!--?= he(get_setting('school_name', SITE_NAME)) ? --></a>.</strong> All rights reserved.
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/script.js"></script>

<script>
(function() {
    const sessionTimeout = <?= SESSION_TIMEOUT ?> * 1000;
    const warningTime = 60 * 1000;
    let warningTimer, logoutTimer;

    function startTimers() {
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);

        warningTimer = setTimeout(function() {
            alert('Your session is about to expire in 1 minute due to inactivity.');
        }, sessionTimeout - warningTime);

        logoutTimer = setTimeout(function() {
            window.location.href = '<?= SITE_URL ?>/logout.php';
        }, sessionTimeout);
    }

    function resetTimers() {
        startTimers();
    }

    startTimers();
    document.addEventListener('mousemove', resetTimers);
    document.addEventListener('keypress', resetTimers);
    document.addEventListener('click', resetTimers);
})();
</script>
<svg id="svgfilters2" xmlns="http://www.w3.org/2000/svg" version="1.1" style="display:none">
<defs>
    <filter id="nnnoise-filter" x="-20%" y="-20%" width="140%" height="140%" filterUnits="objectBoundingBox" primitiveUnits="userSpaceOnUse" color-interpolation-filters="linearRGB">
    <feTurbulence type="fractalNoise" baseFrequency="0.102" numOctaves="4" seed="15" stitchTiles="stitch" x="0%" y="0%" width="100%" height="100%" result="turbulence"></feTurbulence>
    <feSpecularLighting surfaceScale="12" specularConstant="0.4" specularExponent="20" lighting-color="#dfdde1" x="0%" y="0%" width="100%" height="100%" in="turbulence" result="specularLighting">
            <feDistantLight azimuth="3" elevation="142"></feDistantLight>
    </feSpecularLighting>
    </filter>
</defs>
</svg>
</body>
</html>