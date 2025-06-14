<?php
// config/email_config.php

namespace EmailService;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$pathToPhpMailer = dirname(__DIR__) . '/lib/PHPMailer/src/';

require_once $pathToPhpMailer . 'Exception.php';
require_once $pathToPhpMailer . 'PHPMailer.php';
require_once $pathToPhpMailer . 'SMTP.php';

function getConfiguredMailer() {
    $mailer = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mailer->isSMTP();
        $mailer->Host = 'smtp.gmail.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = 'akmalzuhairi01@gmail.com';
        $mailer->Password = 'neii nsfq awam cfpf';
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = 587;
        
        // From address
        $mailer->setFrom('akmalzuhairi01@gmail.com', 'Railway Feedback & Lost and Found');
        
        // Debug settings
        $mailer->SMTPDebug = 0;
        $mailer->Debugoutput = 'html';
        
        // Additional settings
        $mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';
        
    } catch (Exception $e) {
        error_log("PHPMailer configuration error: {$e->getMessage()}");
        throw $e;
    }
    
    return $mailer;
}

function sendWelcomeEmail($userEmail, $firstName) {
    try {
        $mailer = getConfiguredMailer();
        $mailer->addAddress($userEmail);
        
        $mailer->isHTML(true);
        $mailer->Subject = 'Welcome to Railway Feedback & Lost and Found System';
        $mailer->Body = '
            <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; background-color: #f9f9f9;">
                <div style="background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h2 style="color: #003366; text-align: center; margin-bottom: 20px;">Welcome to Railway System!</h2>
                    <p style="font-size: 16px; line-height: 1.6;">Dear <strong>'.$firstName.'</strong>,</p>
                    <p style="font-size: 16px; line-height: 1.6;">Thank you for registering with our Railway Feedback & Lost and Found System.</p>
                    <div style="background-color: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="color: #003366; margin-top: 0;">You can now:</h3>
                        <ul style="padding-left: 20px; line-height: 1.8;">
                            <li>View found items and submit claims</li>
                            <li>Report lost items</li>
                            <li>Submit feedback about your travel experience</li>
                            <li>Track your reports and claims</li>
                        </ul>
                    </div>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                    <p style="font-size: 14px; color: #666; text-align: center;">Railway Feedback & Lost and Found System</p>
                </div>
            </div>';
        
        $mailer->AltBody = "Welcome to Railway System!\n\nDear $firstName,\n\nThank you for registering. You can now submit feedback, report lost items, and track your updates.\n\nBest regards,\nRailway Team";

        return $mailer->send();
    } catch (Exception $e) {
        error_log("Failed to send welcome email: {$e->getMessage()}");
        return false;
    }
}

function sendVerificationCode($userEmail, $verificationCode) {
    try {
        $mailer = getConfiguredMailer();
        $mailer->addAddress($userEmail);
        
        $mailer->isHTML(true);
        $mailer->Subject = 'Railway System - Login Verification Code';
        $mailer->Body = '
            <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; background-color: #f9f9f9;">
                <div style="background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h2 style="color: #003366; text-align: center; margin-bottom: 20px;">🔐 Login Verification</h2>
                    <p style="font-size: 16px; line-height: 1.6; text-align: center;">Enter this verification code to complete your login:</p>
                    
                    <div style="background: linear-gradient(135deg, #003366, #0056b3); padding: 25px; border-radius: 10px; text-align: center; margin: 30px 0;">
                        <div style="background-color: white; padding: 20px; border-radius: 8px; display: inline-block;">
                            <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #003366; font-family: monospace;">'.$verificationCode.'</span>
                        </div>
                    </div>
                    
                    <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0; font-size: 14px; color: #856404;">
                            <strong>⏰ Important:</strong> This code will expire in <strong>10 minutes</strong>
                        </p>
                    </div>
                    
                    <p style="font-size: 14px; color: #666; text-align: center; margin-top: 30px;">
                        If you did not request this code, please ignore this email.
                    </p>
                    
                    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                    <p style="font-size: 12px; color: #999; text-align: center;">
                        Railway Feedback & Lost and Found System<br>
                        This is an automated message, please do not reply.
                    </p>
                </div>
            </div>';
        
        $mailer->AltBody = "Railway System Login Verification\n\nYour verification code is: $verificationCode\n\nThis code will expire in 10 minutes.\n\nIf you did not request this, please ignore this email.";

        return $mailer->send();
    } catch (Exception $e) {
        error_log("Failed to send verification code: {$e->getMessage()}");
        return false;
    }
}

function sendPasswordResetLink($userEmail, $resetToken) {
    try {
        $mailer = getConfiguredMailer();
        $mailer->addAddress($userEmail);
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script_path = dirname($_SERVER['PHP_SELF']);
        $resetLink = $protocol . '://' . $host . $script_path . '/reset_password.php?token=' . urlencode($resetToken);
        
        $mailer->isHTML(true);
        $mailer->Subject = 'Railway System - Password Reset Request';
        $mailer->Body = '
            <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; background-color: #f9f9f9;">
                <div style="background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h2 style="color: #003366; text-align: center; margin-bottom: 20px;">🔑 Password Reset Request</h2>
                    <p style="font-size: 16px; line-height: 1.6;">We received a request to reset your password for your Railway System account.</p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="'.$resetLink.'" style="background: linear-gradient(135deg, #003366, #0056b3); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block;">Reset My Password</a>
                    </div>
                    
                    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #003366;">
                        <p style="margin: 0; font-size: 14px; color: #495057;">
                            <strong>Can\'t click the button?</strong> Copy and paste this link:<br>
                            <span style="word-break: break-all; font-family: monospace; background-color: #e9ecef; padding: 5px; border-radius: 3px;">'.$resetLink.'</span>
                        </p>
                    </div>
                    
                    <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0; font-size: 14px; color: #856404;">
                            <strong>⏰ Security Notice:</strong> This link will expire in <strong>1 hour</strong>.
                        </p>
                    </div>
                    
                    <p style="font-size: 14px; color: #666; line-height: 1.6;">
                        If you did not request this password reset, please ignore this email.
                    </p>
                    
                    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                    <p style="font-size: 12px; color: #999; text-align: center;">
                        Railway Feedback & Lost and Found System
                    </p>
                </div>
            </div>';
        
        $mailer->AltBody = "Railway System Password Reset\n\nClick this link to reset your password: $resetLink\n\nThis link will expire in 1 hour.\n\nIf you did not request this reset, please ignore this email.";

        return $mailer->send();
    } catch (Exception $e) {
        error_log("Failed to send reset link: {$e->getMessage()}");
        return false;
    }
}

function testEmailConnection() {
    try {
        $mailer = getConfiguredMailer();
        return true;
    } catch (Exception $e) {
        error_log("Email connection test failed: {$e->getMessage()}");
        return false;
    }
}
?>