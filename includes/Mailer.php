<?php
/**
 * BeautyOS Mailer — Socket-based SMTP (no Composer needed)
 * Supports plain, SSL, STARTTLS.
 *
 * Usage:
 *   $mail = new Mailer();
 *   $mail->send('user@example.com', 'Jane', 'Subject', '<h1>Body</h1>');
 */
class Mailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;
    private string $fromName;
    private string $enc;   // 'ssl' | 'tls' | 'none'
    private bool   $debug;

    public function __construct()
    {
        $cfg = $this->loadConfig();
        $this->host     = $cfg['host']      ?? 'localhost';
        $this->port     = (int)($cfg['port'] ?? 587);
        $this->user     = $cfg['username']  ?? '';
        $this->pass     = $cfg['password']  ?? '';
        $this->from     = $cfg['from_email'] ?? 'noreply@beautyos.at';
        $this->fromName = $cfg['from_name']  ?? 'BeautyOS';
        $this->enc      = strtolower($cfg['encryption'] ?? 'tls');
        $this->debug    = (bool)($cfg['debug'] ?? false);
    }

    /** Load config from file or DB fallback */
    private function loadConfig(): array
    {
        $file = __DIR__ . '/../config/mail.php';
        if (file_exists($file)) {
            return require $file;
        }
        // Try to read from DB admin settings
        try {
            $db = getDB();
            $rows = $db->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'smtp_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($rows) {
                return [
                    'host'       => $rows['smtp_host']       ?? 'localhost',
                    'port'       => $rows['smtp_port']       ?? 587,
                    'username'   => $rows['smtp_user']       ?? '',
                    'password'   => $rows['smtp_pass']       ?? '',
                    'from_email' => $rows['smtp_from_email'] ?? 'noreply@beautyos.at',
                    'from_name'  => $rows['smtp_from_name']  ?? 'BeautyOS',
                    'encryption' => $rows['smtp_enc']        ?? 'tls',
                ];
            }
        } catch (Throwable $e) { /* no settings table */ }
        return [];
    }

    /**
     * Send an email.
     * @throws RuntimeException on failure
     */
    public function send(string $toEmail, string $toName, string $subject, string $bodyHtml): bool
    {
        if (empty($this->host) || empty($this->user)) {
            error_log('[Mailer] SMTP not configured');
            return false;
        }

        $socket = $this->connect();
        try {
            $this->smtp($socket, null, 220);

            // EHLO
            $domain = gethostname() ?: 'localhost';
            $this->smtp($socket, "EHLO {$domain}", 250);

            if ($this->enc === 'tls') {
                $this->smtp($socket, "STARTTLS", 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtp($socket, "EHLO {$domain}", 250);
            }

            // AUTH LOGIN
            $this->smtp($socket, "AUTH LOGIN", 334);
            $this->smtp($socket, base64_encode($this->user), 334);
            $this->smtp($socket, base64_encode($this->pass), 235);

            // Envelope
            $this->smtp($socket, "MAIL FROM:<{$this->from}>", 250);
            $this->smtp($socket, "RCPT TO:<{$toEmail}>", [250, 251]);

            // DATA
            $this->smtp($socket, "DATA", 354);

            $msgId = '<' . uniqid('bos', true) . '@beautyos.at>';
            $boundary = md5(uniqid('', true));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFrom    = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
            $encodedTo      = '=?UTF-8?B?' . base64_encode($toName) . '?=';

            $headers  = "From: {$encodedFrom} <{$this->from}>\r\n";
            $headers .= "To: {$encodedTo} <{$toEmail}>\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: {$msgId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "X-Mailer: BeautyOS-Mailer/1.0\r\n";

            $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plain)) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($bodyHtml)) . "\r\n";
            $body .= "--{$boundary}--";

            $message = $headers . "\r\n" . $body;
            // Dot-stuffing
            $message = str_replace("\n.", "\n..", $message);
            $this->smtp($socket, $message . "\r\n.", 250);

            $this->smtp($socket, "QUIT", 221);
        } finally {
            fclose($socket);
        }
        return true;
    }

    /** Queue an email to email_queue table */
    public static function queue(string $toEmail, string $toName, string $subject, string $html): void
    {
        try {
            $db = getDB();
            $db->prepare("INSERT INTO email_queue (to_email, to_name, subject, body_html) VALUES (?,?,?,?)")
               ->execute([$toEmail, $toName, $subject, $html]);
        } catch (Throwable $e) {
            error_log('[Mailer::queue] ' . $e->getMessage());
        }
    }

    /** Process pending queue (call from cron or inline) */
    public function processQueue(int $limit = 20): int
    {
        $db = getDB();
        $rows = $db->query("SELECT * FROM email_queue WHERE status='pending' AND attempts < 3 ORDER BY created_at LIMIT {$limit}")->fetchAll();
        $sent = 0;
        foreach ($rows as $row) {
            try {
                $this->send($row['to_email'], $row['to_name'] ?? '', $row['subject'], $row['body_html']);
                $db->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$row['id']]);
                $sent++;
            } catch (Throwable $e) {
                $db->prepare("UPDATE email_queue SET attempts=attempts+1, last_error=? WHERE id=?")
                   ->execute([$e->getMessage(), $row['id']]);
                if ($row['attempts'] + 1 >= 3) {
                    $db->prepare("UPDATE email_queue SET status='failed' WHERE id=?")->execute([$row['id']]);
                }
            }
        }
        return $sent;
    }

    // ─── SMTP Command ──────────────────────────────────────────────────────────

    private function connect()
    {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        $wrapper = $this->enc === 'ssl' ? 'ssl' : 'tcp';
        $errno = $errstr = null;
        $socket = stream_socket_client(
            "{$wrapper}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$socket) {
            throw new RuntimeException("SMTP connect failed ({$this->host}:{$this->port}): {$errstr}");
        }
        stream_set_timeout($socket, 15);
        return $socket;
    }

    private function smtp($socket, ?string $command, $expectCode): string
    {
        if ($command !== null) {
            if ($this->debug) error_log("[SMTP>] {$command}");
            fwrite($socket, $command . "\r\n");
        }
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;  // last line of response
        }
        if ($this->debug) error_log("[SMTP<] " . trim($response));
        $code = (int)substr(trim($response), 0, 3);
        $expected = is_array($expectCode) ? $expectCode : [$expectCode];
        if ($expectCode !== null && !in_array($code, $expected)) {
            throw new RuntimeException("SMTP error (expected " . implode('/', $expected) . ", got {$code}): " . trim($response));
        }
        return $response;
    }

    // ─── Email Templates ────────────────────────────────────────────────────────

    public static function templateWrap(string $title, string $body): string
    {
        $appName = APP_NAME;
        $appUrl  = APP_URL;
        $year    = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<style>
body { margin:0; padding:0; background:#f5f0eb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; color:#424242; }
.email-wrapper { max-width:600px; margin:0 auto; }
.email-header { background:linear-gradient(135deg,#d4809f,#e8a0bf); padding:32px 40px; text-align:center; }
.email-logo { color:#fff; font-size:1.5rem; font-weight:800; letter-spacing:-0.5px; }
.email-logo span { opacity:0.85; }
.email-body { background:#ffffff; padding:40px; }
.email-footer { padding:24px 40px; text-align:center; font-size:0.8rem; color:#9e9e9e; }
.btn-primary { display:inline-block; background:linear-gradient(135deg,#d4809f,#e8a0bf); color:#fff!important; padding:14px 32px; border-radius:12px; text-decoration:none; font-weight:700; font-size:0.95rem; margin:20px 0; }
.divider { border:none; border-top:1px solid #f0ebf5; margin:24px 0; }
h1,h2 { color:#212121; font-weight:800; }
p { line-height:1.7; color:#616161; }
.highlight { color:#d4809f; font-weight:700; }
</style>
</head>
<body>
<div class="email-wrapper">
  <div class="email-header">
    <div class="email-logo">Beauty<span>OS</span></div>
  </div>
  <div class="email-body">
    {$body}
  </div>
  <div class="email-footer">
    &copy; {$year} {$appName} &nbsp;&middot;&nbsp;
    <a href="{$appUrl}/datenschutz.php" style="color:#9e9e9e;">Datenschutz</a> &nbsp;&middot;&nbsp;
    <a href="{$appUrl}/impressum.php" style="color:#9e9e9e;">Impressum</a>
  </div>
</div>
</body>
</html>
HTML;
    }

    public static function sendWelcome(string $email, string $name): void
    {
        $body = "<h1>Willkommen bei BeautyOS, {$name}! 👋</h1>
<p>Schön, dass du dabei bist! Mit deinem neuen Konto kannst du jetzt:</p>
<ul>
<li>🔍 Tausende Beauty-Anbieter in deiner Nähe entdecken</li>
<li>📅 Termine einfach online buchen</li>
<li>⭐ Bewertungen schreiben und teilen</li>
<li>❤️ Deine Lieblings-Salons speichern</li>
</ul>
<p style='text-align:center;'><a href='" . APP_URL . "/marketplace.php' class='btn-primary'>Jetzt entdecken</a></p>
<hr class='divider'>
<p style='font-size:0.85rem;'>Falls du Fragen hast, antworte einfach auf diese E-Mail.</p>";
        self::queue($email, $name, 'Willkommen bei BeautyOS! 🌸', self::templateWrap('Willkommen', $body));
    }

    public static function sendBookingConfirmation(array $booking, array $business, array $service, array $user): void
    {
        $date = date('d.m.Y', strtotime($booking['booking_date']));
        $time = substr($booking['booking_time'], 0, 5);
        $price = number_format($booking['total_price'] ?? $service['price'], 2, ',', '.') . ' €';
        $body = "<h1>Buchung bestätigt ✅</h1>
<p>Hallo <strong>{$user['first_name']}</strong>, deine Buchung wurde erfolgreich erstellt!</p>
<div style='background:#fdf2f8;border-radius:12px;padding:20px;margin:20px 0;'>
<table width='100%' cellpadding='6'>
<tr><td style='color:#9080b0;font-size:0.85rem;'>SALON</td><td><strong>" . htmlspecialchars($business['name']) . "</strong></td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>SERVICE</td><td>" . htmlspecialchars($service['name']) . "</td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>DATUM</td><td><strong>{$date}</strong></td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>UHRZEIT</td><td><strong>{$time} Uhr</strong></td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>PREIS</td><td><strong>{$price}</strong></td></tr>
</table>
</div>
<p>Bitte erscheine pünktlich. Bei Änderungen kontaktiere den Salon direkt.</p>
<p style='text-align:center;'><a href='" . APP_URL . "/bookings.php' class='btn-primary'>Meine Buchungen</a></p>";
        self::queue($user['email'], $user['first_name'], "Buchungsbestätigung — {$business['name']}", self::templateWrap('Buchungsbestätigung', $body));
    }

    public static function sendBookingStatusUpdate(array $booking, array $business, array $user, string $newStatus): void
    {
        $statusLabels = [
            'confirmed'  => ['Bestätigt ✅',   'Deine Buchung wurde vom Salon bestätigt.'],
            'cancelled'  => ['Storniert ❌',    'Deine Buchung wurde leider storniert.'],
            'completed'  => ['Abgeschlossen 🌟', 'Wir hoffen, dir hat der Besuch gefallen!'],
        ];
        [$statusTitle, $statusMsg] = $statusLabels[$newStatus] ?? [$newStatus, ''];
        $date = date('d.m.Y', strtotime($booking['booking_date']));
        $body = "<h1>Buchung {$statusTitle}</h1>
<p>Hallo <strong>{$user['first_name']}</strong>, {$statusMsg}</p>
<div style='background:#fdf2f8;border-radius:12px;padding:20px;margin:20px 0;'>
<p><strong>" . htmlspecialchars($business['name']) . "</strong> — {$date}</p>
</div>" .
($newStatus === 'completed' ? "<p style='text-align:center;'><a href='" . APP_URL . "/business.php?slug={$business['slug']}' class='btn-primary'>Bewertung schreiben ⭐</a></p>" : "");
        self::queue($user['email'], $user['first_name'], "Buchungs-Update: {$statusTitle}", self::templateWrap('Buchungs-Update', $body));
    }

    public static function sendBusinessApproved(array $business, array $user): void
    {
        $body = "<h1>Dein Business ist live! 🎉</h1>
<p>Hallo <strong>{$user['first_name']}</strong>, dein Unternehmen <strong>" . htmlspecialchars($business['name']) . "</strong> wurde von uns geprüft und ist jetzt auf BeautyOS sichtbar.</p>
<p style='text-align:center;'><a href='" . APP_URL . "/dashboard/index.php' class='btn-primary'>Zum Dashboard</a></p>
<hr class='divider'>
<p style='font-size:0.85rem;'>Jetzt dein Profil vervollständigen, Öffnungszeiten eintragen und erste Bewertungen sammeln!</p>";
        self::queue($user['email'], $user['first_name'], 'Dein BeautyOS-Profil ist jetzt live! 🌸', self::templateWrap('Profil live', $body));
    }

    public static function sendBusinessRejected(array $business, array $user, string $reason = ''): void
    {
        $reasonHtml = $reason ? "<p><strong>Begründung:</strong> " . htmlspecialchars($reason) . "</p>" : '';
        $body = "<h1>Profil-Überprüfung</h1>
<p>Hallo <strong>{$user['first_name']}</strong>, dein Profil <strong>" . htmlspecialchars($business['name']) . "</strong> wurde leider noch nicht freigeschaltet.</p>
{$reasonHtml}
<p>Bitte ergänze fehlende Informationen und kontaktiere uns bei Fragen.</p>
<p style='text-align:center;'><a href='" . APP_URL . "/dashboard/settings.php' class='btn-primary'>Profil bearbeiten</a></p>";
        self::queue($user['email'], $user['first_name'], 'Profil-Überprüfung erforderlich', self::templateWrap('Profil-Überprüfung', $body));
    }

    public static function sendPasswordReset(string $email, string $name, string $token): void
    {
        $link = APP_URL . '/reset-password.php?token=' . $token;
        $body = "<h1>Passwort zurücksetzen</h1>
<p>Hallo <strong>{$name}</strong>, du hast eine Passwort-Zurücksetzung angefordert.</p>
<p style='text-align:center;'><a href='{$link}' class='btn-primary'>Neues Passwort setzen</a></p>
<p style='font-size:0.85rem;color:#9e9e9e;'>Dieser Link ist 1 Stunde gültig. Falls du die Anfrage nicht gestellt hast, ignoriere diese E-Mail.</p>";
        self::queue($email, $name, 'Passwort zurücksetzen — BeautyOS', self::templateWrap('Passwort zurücksetzen', $body));
    }

    public static function sendSubscriptionConfirmation(array $user, array $business, array $plan): void
    {
        $price = number_format($plan['price_monthly'], 2, ',', '.') . ' €/Monat';
        $body = "<h1>Abo aktiviert ✅</h1>
<p>Hallo <strong>{$user['first_name']}</strong>, dein <span class='highlight'>{$plan['name']}</span>-Abo für <strong>" . htmlspecialchars($business['name']) . "</strong> ist jetzt aktiv!</p>
<div style='background:#fdf2f8;border-radius:12px;padding:20px;margin:20px 0;'>
<p><strong>Plan:</strong> {$plan['name']}<br><strong>Preis:</strong> {$price}</p>
</div>
<p style='text-align:center;'><a href='" . APP_URL . "/dashboard/subscription.php' class='btn-primary'>Abo verwalten</a></p>";
        self::queue($user['email'], $user['first_name'], "Abo bestätigt: {$plan['name']}", self::templateWrap('Abo bestätigt', $body));
    }

    public static function sendNewBookingNotification(array $booking, array $service, array $customer, array $business, array $businessUser): void
    {
        $date = date('d.m.Y', strtotime($booking['booking_date']));
        $time = substr($booking['booking_time'], 0, 5);
        $body = "<h1>Neue Buchung 📅</h1>
<p>Hallo <strong>{$businessUser['first_name']}</strong>, du hast eine neue Buchungsanfrage!</p>
<div style='background:#fdf2f8;border-radius:12px;padding:20px;margin:20px 0;'>
<table width='100%' cellpadding='6'>
<tr><td style='color:#9080b0;font-size:0.85rem;'>KUNDE</td><td><strong>" . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . "</strong></td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>SERVICE</td><td>" . htmlspecialchars($service['name']) . "</td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>DATUM</td><td><strong>{$date}</strong></td></tr>
<tr><td style='color:#9080b0;font-size:0.85rem;'>UHRZEIT</td><td><strong>{$time} Uhr</strong></td></tr>
</table>
</div>
<p style='text-align:center;'><a href='" . APP_URL . "/dashboard/index.php?tab=bookings' class='btn-primary'>Jetzt bestätigen</a></p>";
        self::queue($businessUser['email'], $businessUser['first_name'], "Neue Buchung von " . $customer['first_name'], self::templateWrap('Neue Buchung', $body));
    }
}
