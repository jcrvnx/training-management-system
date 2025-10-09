<?php
/**
 * Simplified PHPMailer class for Training Management System
 * Based on PHPMailer v6.x functionality
 */

class PHPMailer
{
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    
    public $isSMTP = false;
    public $Host = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $Port = 587;
    public $FromName = '';
    public $From = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $isHTML = true;
    public $CharSet = 'UTF-8';
    
    private $to = [];
    private $cc = [];
    private $bcc = [];
    private $replyTo = [];
    private $attachments = [];
    private $customHeaders = [];
    
    public function __construct($exceptions = null)
    {
        // Constructor
    }
    
    public function isSMTP()
    {
        $this->isSMTP = true;
    }
    
    public function addAddress($address, $name = '')
    {
        $this->to[] = ['email' => $address, 'name' => $name];
        return true;
    }
    
    public function addCC($address, $name = '')
    {
        $this->cc[] = ['email' => $address, 'name' => $name];
        return true;
    }
    
    public function addBCC($address, $name = '')
    {
        $this->bcc[] = ['email' => $address, 'name' => $name];
        return true;
    }
    
    public function addReplyTo($address, $name = '')
    {
        $this->replyTo[] = ['email' => $address, 'name' => $name];
        return true;
    }
    
    public function addAttachment($path, $name = '')
    {
        if (file_exists($path)) {
            $this->attachments[] = ['path' => $path, 'name' => $name ?: basename($path)];
            return true;
        }
        return false;
    }
    
    public function setFrom($address, $name = '')
    {
        $this->From = $address;
        $this->FromName = $name;
        return true;
    }
    
    public function send()
    {
        if (empty($this->to)) {
            return false;
        }
        
        // Build headers
        $headers = $this->buildHeaders();
        
        // Prepare recipients
        $to_addresses = [];
        foreach ($this->to as $recipient) {
            $to_addresses[] = $recipient['name'] ? "{$recipient['name']} <{$recipient['email']}>" : $recipient['email'];
        }
        $to_string = implode(', ', $to_addresses);
        
        // Send email using PHP's mail() function
        if ($this->isSMTP) {
            // For SMTP, we'll use a simple implementation
            return $this->sendViaSMTP($to_string, $headers);
        } else {
            // Use PHP mail() function
            $body = $this->isHTML ? $this->Body : strip_tags($this->Body);
            return mail($to_string, $this->Subject, $body, $headers);
        }
    }
    
    private function buildHeaders()
    {
        $headers = [];
        
        // From header
        if ($this->From) {
            $from = $this->FromName ? "{$this->FromName} <{$this->From}>" : $this->From;
            $headers[] = "From: $from";
        }
        
        // Reply-To header
        if (!empty($this->replyTo)) {
            $replyTo = $this->replyTo[0];
            $replyToStr = $replyTo['name'] ? "{$replyTo['name']} <{$replyTo['email']}>" : $replyTo['email'];
            $headers[] = "Reply-To: $replyToStr";
        }
        
        // CC headers
        if (!empty($this->cc)) {
            $cc_addresses = [];
            foreach ($this->cc as $cc) {
                $cc_addresses[] = $cc['name'] ? "{$cc['name']} <{$cc['email']}>" : $cc['email'];
            }
            $headers[] = "CC: " . implode(', ', $cc_addresses);
        }
        
        // BCC headers
        if (!empty($this->bcc)) {
            $bcc_addresses = [];
            foreach ($this->bcc as $bcc) {
                $bcc_addresses[] = $bcc['name'] ? "{$bcc['name']} <{$bcc['email']}>" : $bcc['email'];
            }
            $headers[] = "BCC: " . implode(', ', $bcc_addresses);
        }
        
        // Content headers
        $headers[] = "MIME-Version: 1.0";
        if ($this->isHTML) {
            $headers[] = "Content-Type: text/html; charset={$this->CharSet}";
        } else {
            $headers[] = "Content-Type: text/plain; charset={$this->CharSet}";
        }
        
        // Additional headers
        $headers[] = "X-Mailer: TMS-PHPMailer";
        
        return implode("\r\n", $headers);
    }
    
    private function sendViaSMTP($to, $headers)
    {
        // Simple SMTP implementation for basic functionality
        // In production, you would want to use a more robust SMTP library
        
        if (!$this->Host) {
            return false;
        }
        
        // For now, fall back to mail() function with headers
        // This can be enhanced with actual SMTP socket connection
        $body = $this->isHTML ? $this->Body : strip_tags($this->Body);
        return mail($to, $this->Subject, $body, $headers);
    }
    
    public function clearAddresses()
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
    }
    
    public function clearAttachments()
    {
        $this->attachments = [];
    }
    
    public function clearReplyTos()
    {
        $this->replyTo = [];
    }
    
    public function clearAllRecipients()
    {
        $this->clearAddresses();
    }
    
    public function isHTML($isHTML = true)
    {
        $this->isHTML = $isHTML;
        return true;
    }
}
?>