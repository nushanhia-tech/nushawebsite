<footer class="nf-footer" aria-label="Site footer">
    <div class="nf-wrap">
        <div class="nf-topline">
            <p><span class="nf-dot" aria-hidden="true"></span> Knowledge brings us together.</p>
            <a href="contact.php">Get in touch <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="nf-grid">
            <div class="nf-brand-col">
                <a href="index.php" class="nf-brand" aria-label="NUS'HA home">
                    <span class="nusha-mark" aria-hidden="true"></span>
                    <span><strong>NUS'HA</strong><small>Noorul Hidaya<br>Students' Union</small></span>
                </a>
                <p class="nf-description">A community inspired by knowledge, united by purpose, and committed to growing together.</p>
                <div class="nf-socials" aria-label="Follow NUS'HA">
                    <a href="https://www.instagram.com/nusha_pattambi?utm_source=ig_web_button_share_sheet&amp;psln=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener noreferrer" aria-label="Instagram (opens in a new tab)"><i class="fa-brands fa-instagram" aria-hidden="true"></i> Instagram <i class="fa-solid fa-arrow-up-right-from-square nf-external" aria-hidden="true"></i></a>
                    <a href="https://www.youtube.com/@nushapattambi" target="_blank" rel="noopener noreferrer" aria-label="YouTube (opens in a new tab)"><i class="fa-brands fa-youtube" aria-hidden="true"></i> YouTube <i class="fa-solid fa-arrow-up-right-from-square nf-external" aria-hidden="true"></i></a>
                </div>
            </div>
            <nav class="nf-links" aria-label="Footer navigation">
                <h3>Discover</h3>
                <a href="about.php">About us</a>
                <a href="leadership.php">Our leadership</a>
                <a href="events.php">Programmes</a>
                <a href="ranking.php">Rankings</a>
            </nav>
            <nav class="nf-links" aria-label="Footer resources">
                <h3>Quick access</h3>
                <a href="/portals?role=student" data-portal-login="student">Student portal</a>
                <a href="calendar.php">Event calendar</a>
                <a href="contact.php">Contact us</a>
                <a href="index.php">Home</a>
            </nav>
            <div class="nf-contact">
                <h3>Let's connect</h3>
                <div class="nf-contact-row">
                    <span class="nf-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                    <div><small>Our campus</small><p>Noorul Hidaya Islamic Academy<br>Pattambi, Kerala</p></div>
                </div>
                <div class="nf-contact-row">
                    <span class="nf-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                    <div><small>Call us</small><a href="tel:+918848339797">+91 884 833 9797</a></div>
                </div>
                <div class="nf-contact-row">
                    <span class="nf-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></span>
                    <div><small>Email us</small><a href="mailto:nushapattambi@gmail.com">nushapattambi@gmail.com</a></div>
                </div>
            </div>
        </div>
        <div class="nf-bottom">
            <p>&copy; <?= (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y') ?> Noorul Hidaya Students' Union. All rights reserved.</p>
            <a href="#main-header">Back to top <span><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></span></a>
        </div>
    </div>
</footer>