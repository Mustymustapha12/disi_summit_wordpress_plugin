<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Email {

    public static function send_approval_email(
        $registration
    ) {

        if (empty($registration->email)) {
            return false;
        }

        $payment_link =
        esc_url(
            $registration->paystack_authorization_url ?? ''
        );

        $registration_number =
        DISI_Registration_Manager::get_registration_number(
            $registration
        );

        $name =
        self::recipient_name($registration);

        $total_amount =
        self::format_amount(
            $registration->total_amount ?? 0
        );

        $type =
        DISI_Registration_Manager::label_registration_type(
            $registration->registration_type
        );

        $message =
        self::email_shell(
            'DISI Summit 2026 Registration Approved',
            '
            <p>Dear ' . esc_html($name) . ',</p>

            <p>
                Congratulations. Your DISI Summit 2026 registration
                has been approved. Please proceed with payment to
                complete your registration.
            </p>

            <table cellpadding="8" style="width:100%;margin:20px 0;border-collapse:collapse;">
                <tr>
                    <td>Registration Number</td>
                    <td><strong>' . esc_html($registration_number) . '</strong></td>
                </tr>
                <tr>
                    <td>Registration Type</td>
                    <td><strong>' . esc_html($type) . '</strong></td>
                </tr>
                <tr>
                    <td>Registration Amount</td>
                    <td><strong>' . esc_html(self::format_amount($registration->registration_amount ?? 0)) . '</strong></td>
                </tr>
                <tr>
                    <td>Workshop Add-on</td>
                    <td><strong>' . esc_html(self::format_amount($registration->workshop_amount ?? 0)) . '</strong></td>
                </tr>
                <tr>
                    <td>Total Amount to Pay</td>
                    <td><strong>' . esc_html($total_amount) . '</strong></td>
                </tr>
                <tr>
                    <td>Payment Reference</td>
                    <td><strong>' . esc_html($registration->paystack_reference ?? '') . '</strong></td>
                </tr>
            </table>

            <p>Click the button below to make payment.</p>

            ' . self::payment_button($payment_link) . '
            '
        );

        return wp_mail(
            $registration->email,
            'DISI Summit 2026 Registration Approved - Proceed With Payment',
            $message,
            self::headers()
        );
    }

    public static function send_rejection_email(
        $registration
    ) {

        if (empty($registration->email)) {
            return false;
        }

        $registration_number =
        DISI_Registration_Manager::get_registration_number(
            $registration
        );

        $reason =
        trim(
            $registration->rejection_reason ?? ''
        );

        $message =
        self::email_shell(
            'DISI Summit 2026 Registration Update',
            '
            <p>Dear ' . esc_html(self::recipient_name($registration)) . ',</p>

            <p>
                Thank you for your interest in DISI Summit 2026.
                After reviewing your registration, we are unable to
                approve it at this time.
            </p>

            <p>
                Registration Number:
                <strong>' . esc_html($registration_number) . '</strong>
            </p>

            ' . (
                !empty($reason)
                ? '<p><strong>Reason:</strong> ' . esc_html($reason) . '</p>'
                : ''
            ) . '

            <p>
                If you believe this was a mistake, please contact the
                DISI Summit team for assistance.
            </p>
            '
        );

        return wp_mail(
            $registration->email,
            'DISI Summit 2026 Registration Update',
            $message,
            self::headers()
        );
    }

    private static function email_shell(
        $title,
        $body
    ) {

        $logo =
        DISI_PLUGIN_URL .
        'assets/images/disi-logo.png';

        return '
        <html>
        <body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">
            <div style="max-width:720px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;border-top:8px solid #ffc801;">
                <div style="background:#172b3b;padding:30px;text-align:center;">
                    <img src="' . esc_url($logo) . '" alt="DISI" style="max-width:220px;height:auto;">
                </div>

                <div style="height:8px;background:linear-gradient(90deg,#157664 0%,#53965c 35%,#a5b73c 70%,#ffc801 100%);"></div>

                <div style="padding:40px;color:#172b3b;font-size:15px;line-height:1.6;">
                    <h2 style="margin-top:0;color:#157664;">' . esc_html($title) . '</h2>
                    ' . $body . '
                </div>
            </div>
        </body>
        </html>
        ';
    }

    private static function payment_button($payment_link) {

        if (empty($payment_link)) {
            return '
            <p>
                Payment details will be shared with you shortly.
            </p>
            ';
        }

        return '
        <p style="text-align:center;margin:40px 0;">
            <a href="' . esc_url($payment_link) . '"
            style="background:#ffc801;color:#172b3b;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
                Pay Now
            </a>
        </p>

        <p>
            If the button does not work, copy and paste this link into
            your browser:
            <a href="' . esc_url($payment_link) . '">' .
                esc_html($payment_link) .
            '</a>
        </p>
        ';
    }

    private static function recipient_name($registration) {

        $name =
        trim(
            ($registration->first_name ?? '') .
            ' ' .
            ($registration->last_name ?? '')
        );

        return !empty($name)
        ? $name
        : 'Participant';
    }

    private static function format_amount($amount) {

        $amount =
        floatval($amount);

        return 'NGN ' .
        number_format(
            $amount,
            2
        );
    }

    private static function headers() {

        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: DISI Summit 2026 <noreply@disisummit.org>'
        ];
    }
}
