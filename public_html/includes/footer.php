</main>
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-row">
            <div>&copy; <?= date('Y') ?> Cyber4rall — NDPR Compliance Tracker</div>
            <div class="footer-links">
                <a href="/privacy.php">Privacy Policy</a>
                <a href="/terms.php">Terms &amp; Conditions</a>
                <a href="mailto:<?= e(BRAND_CONTACT_EMAIL) ?>"><?= e(BRAND_CONTACT_EMAIL) ?></a>
            </div>
            <div class="footer-socials">
                <?php foreach (SOCIAL_LINKS as $name => $url): ?>
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="Cyber4rall on <?= e($name) ?>"><?= e($name) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</footer>

<div id="cookie-notice" class="cookie-notice no-print">
    <p>We use only the essential cookies needed to keep you signed in — no tracking or advertising cookies. See our <a href="/privacy.php">Privacy Policy</a>.</p>
    <button id="cookie-ack" class="btn btn-small">Got it</button>
</div>
<script>
(function(){
    var KEY = 'c4a_cookie_ack';
    var notice = document.getElementById('cookie-notice');
    if (!document.cookie.split('; ').some(function(c){ return c.indexOf(KEY + '=') === 0; })) {
        notice.classList.add('visible');
    }
    document.getElementById('cookie-ack').addEventListener('click', function(){
        var d = new Date(); d.setFullYear(d.getFullYear() + 1);
        document.cookie = KEY + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
        notice.classList.remove('visible');
    });
})();
</script>
</body>
</html>
