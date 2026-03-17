    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <a href="/" class="navbar-logo" style="color: white; margin-bottom: 16px;">
                        <span class="logo-dot"></span>
                        Beauty<span>OS</span>
                    </a>
                    <p>Dein Marktplatz für Beauty & Wellness. Finde die besten Salons, Studios und Dienstleister in deiner Nähe.</p>
                </div>
                <div>
                    <h4>Entdecken</h4>
                    <ul class="footer-links">
                        <li><a href="/marketplace.php">Marktplatz</a></li>
                        <li><a href="/marketplace.php?category=friseur">Friseure</a></li>
                        <li><a href="/marketplace.php?category=nagelstudio">Nagelstudios</a></li>
                        <li><a href="/marketplace.php?category=kosmetik">Kosmetik</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Für Unternehmen</h4>
                    <ul class="footer-links">
                        <li><a href="/pricing.php">Preise</a></li>
                        <li><a href="/register.php?type=business">Business-Konto</a></li>
                        <li><a href="/dashboard/index.php">Dashboard</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Rechtliches</h4>
                    <ul class="footer-links">
                        <li><a href="/impressum">Impressum</a></li>
                        <li><a href="/datenschutz">Datenschutz</a></li>
                        <li><a href="/agb">AGB</a></li>
                        <li><a href="#">Kontakt</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> BeautyOS. Alle Rechte vorbehalten.</span>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-pinterest"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- App JS -->
    <script src="/assets/js/app.js"></script>
    <!-- Analytics Tracking -->
    <script>
    (function(){
        var page = window.location.pathname;
        fetch('/api/track.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ event_type:'page_view', page: page }),
            keepalive: true
        }).catch(function(){});
    })();
    </script>
</body>
</html>
