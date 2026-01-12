<?php
/**
 * Email Handler Class
 * Handles sending emails using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email.php';

class Email {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }
    
    /**
     * Configure PHPMailer with SMTP settings
     */
    private function configure() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = SMTP_AUTH;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_ENCRYPTION;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = EMAIL_CHARSET;
            
            // Debug mode
            $this->mailer->SMTPDebug = EMAIL_DEBUG;
            
            // Default sender
            $this->mailer->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
            $this->mailer->addReplyTo(EMAIL_REPLY_TO, EMAIL_FROM_NAME);
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
        }
    }
    
    /**
     * Send an email
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $body HTML body
     * @param string $altBody Plain text alternative
     * @return array Result with success status and message
     */
    public function send($to, $subject, $body, $altBody = '') {
        if (!EMAIL_ENABLED) {
            return ['success' => false, 'message' => 'Email notifications are disabled'];
        }
        
        try {
            // Reset recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Add recipient(s)
            if (is_array($to)) {
                foreach ($to as $email) {
                    if ($this->isValidEmail($email)) {
                        $this->mailer->addAddress($email);
                    }
                }
            } else {
                if (!$this->isValidEmail($to)) {
                    return ['success' => false, 'message' => 'Invalid email address'];
                }
                $this->mailer->addAddress($to);
            }
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);
            
            // Send
            $this->mailer->send();
            
            // Log success
            $this->logEmail($to, $subject, 'sent');
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("Email send error: " . $this->mailer->ErrorInfo);
            $this->logEmail($to, $subject, 'failed', $this->mailer->ErrorInfo);
            return ['success' => false, 'message' => 'Email sending failed: ' . $this->mailer->ErrorInfo];
        }
    }
    
    /**
     * Send email using template
     * 
     * @param string|array $to Recipient email(s)
     * @param string $template Template name
     * @param array $data Template variables
     * @return array Result
     */
    public function sendTemplate($to, $template, $data = []) {
        $templatePath = __DIR__ . '/../includes/email-templates/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            return ['success' => false, 'message' => 'Template not found'];
        }
        
        // Extract data for template
        extract($data);
        
        // Capture template output
        ob_start();
        include $templatePath;
        $body = ob_get_clean();
        
        $subject = $data['subject'] ?? 'Notification from ' . APP_NAME;
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Validate email address
     */
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Log email to database
     */
    private function logEmail($to, $subject, $status, $error = null) {
        global $pdo;
        
        try {
            $recipient = is_array($to) ? implode(', ', $to) : $to;
            
            $sql = "INSERT INTO email_notifications (recipient_email, subject, status, error_message, sent_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$recipient, $subject, $status, $error]);
        } catch (PDOException $e) {
            error_log("Email logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Test email configuration
     */
    public static function testConnection() {
        $email = new self();
        
        try {
            $email->mailer->smtpConnect();
            $email->mailer->smtpClose();
            return ['success' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }
}
?>
