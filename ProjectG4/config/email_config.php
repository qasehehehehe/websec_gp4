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
    
    // Server settings
    $mailer->SMTPDebug = 0;
    $mailer->isSMTP();
    $mailer->Host = 'smtp.gmail.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = 'fitrifatiha1@gmail.com';
    $mailer->Password = 'skfp hbyi ukyr dszp'; // password - apppassword
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = 587;
    $mailer->setFrom('fitrifatiha1@gmail.com', 'Railway Feedback & Lost and Found');
    
    return $mailer;
}

function sendWelcomeEmail($userEmail, $firstName) {
    try {
        $mailer = getConfiguredMailer();
        $mailer->addAddress($userEmail);
        
        $mailer->isHTML(true);
        $mailer->Subject = 'Welcome to Railway Feedback & Lost and Found System';
        $mailer->Body = '
            <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #003366;">Welcome to Railway Feedback & Lost and Found!</h2>
                <p>Dear '.$firstName.',</p>
                <p>Thank you for registering with the Railway Feedback & Lost and Found System. We are excited to have you on board.</p>
                <p>As a member, you can now:</p>
                <ul style="padding-left: 20px;">
                    <li>Submit feedback about your railway travel experience</li>
                    <li>Report lost items and view found item reports</li>
                    <li>Track the status of your reports and feedback</li>
                    <li>Receive timely updates and notifications</li>
                </ul>
                <p>Your participation helps improve our services and increases the chances of reuniting items with their rightful owners.</p>
                <p>If you have any questions or need help, feel free to contact our support team.</p>
                <p style="margin-top: 20px;">Best regards,<br>The Railway Feedback & Lost and Found Team</p>
            </div>';
        $mailer->AltBody = "Welcome to Railway Feedback & Lost and Found!\n\nDear $firstName,\n\nThank you for registering. You can now submit feedback, report lost items, and track your updates.\n\nBest regards,\nRailway Feedback & Lost and Found Team";

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
        $mailer->Subject = 'Login Verification Code';
        $mailer->Body = '
            <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #003366;">Verification Code</h2>
                <p>Your verification code is:</p>
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; letter-spacing: 5px; margin: 20px 0;">
                    <strong>'.$verificationCode.'</strong>
                </div>
                <p>This code will expire in 10 minutes.</p>
                <p style="color: #666; font-size: 12px;">If you did not request this code, please ignore this email.</p>
            </div>';
        $mailer->AltBody = "Your verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

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
        
        // Use absolute path for the reset link
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . urlencode($resetToken);
        
        $mailer->isHTML(true);
        $mailer->Subject = 'Password Reset Request';
        $mailer->Body = '
            <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #003366;">Password Reset Request</h2>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="'.$resetLink.'" style="background-color: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Reset Password</a>
                </div>
                <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
                <p style="word-break: break-all; background-color: #f5f5f5; padding: 10px; border-radius: 5px;">'.$resetLink.'</p>
                <p style="color: #666;">This link will expire in 1 hour.</p>
                <p style="color: #666; font-size: 12px;">If you did not request this reset, please ignore this email.</p>
            </div>';
        $mailer->AltBody = "Reset your password by clicking this link: $resetLink \n\nThis link will expire in 1 hour. If you did not request this reset, please ignore this email.";

        return $mailer->send();
    } catch (Exception $e) {
        error_log("Failed to send reset link: {$e->getMessage()}");
        return false;
    }
}
?>