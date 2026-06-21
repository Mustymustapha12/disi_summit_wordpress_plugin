<?php

if (!defined('ABSPATH')) {
    exit;
}
/*
|--------------------------------------------------------------------------
| Handle Actions
|--------------------------------------------------------------------------
*/

$id = intval($_GET['id'] ?? 0);

if (
    isset($_POST['disi_approve_registration']) &&
    check_admin_referer('disi_approve_' . $id)
) {
    $approval = DISI_Registration_Manager::approve(
        $id,
        $_POST['group_custom_amount'] ?? 0
    );

    if (is_wp_error($approval)) {
        echo '
        <div class="notice notice-error">
            <p>' . esc_html($approval->get_error_message()) . '</p>
        </div>
        ';
    } else {
        echo '
        <div class="notice notice-success">
            <p>
                Registration approved and Paystack payment link generated.
            </p>
        </div>
        ';
    }
}

if (
    isset($_POST['disi_reject_registration']) &&
    check_admin_referer(
        'disi_reject_' .
        intval($_GET['id'])
    )
) {

    DISI_Registration_Manager::reject(
        intval($_GET['id']),
        sanitize_textarea_field(
            $_POST['rejection_reason'] ?? ''
        )
    );

    echo '
    <div class="notice notice-success">
    <p>
    Registration rejected successfully.
    </p>
    </div>
    ';
}

if (
    isset($_POST['disi_verify_payment']) &&
    check_admin_referer('disi_verify_payment_' . $id)
) {
    $verification = DISI_Registration_Manager::verify_payment(
        sanitize_text_field(
            wp_unslash($_POST['paystack_reference'] ?? '')
        )
    );

    if (is_wp_error($verification)) {
        echo '
        <div class="notice notice-error">
            <p>' . esc_html($verification->get_error_message()) . '</p>
        </div>
        ';
    } else {
        echo '
        <div class="notice notice-success">
            <p>Payment verified successfully.</p>
        </div>
        ';
    }
}

$registration =
DISI_Registration_Manager::get($id);

if (!$registration) {

    echo '<div class="notice notice-error">
    <p>Registration not found.</p>
    </div>';

    return;
}

$data = json_decode(
    $registration->submitted_data,
    true
);

?>

<div class="wrap">

<div class="disi-details-container">

<h1>

Registration Details

</h1>

<div class="disi-summary-card">

    <div>

    <strong>
        Registration Number
    </strong>

    <br>

    <?php

        echo esc_html(

        DISI_Registration_Manager::get_registration_number(
            $registration
        )

    );

?>

</div>

<div>

<strong>Type:</strong>

<?php
echo esc_html(
DISI_Registration_Manager::label_registration_type(
    $registration->registration_type
)
);
?>

</div>

<div>

<strong>Status:</strong>

<span class="disi-status-badge disi-<?php echo esc_attr($registration->status); ?>">

<?php
echo esc_html(
ucfirst(
$registration->status
)
);
?>

</span>

</div>

<div>

<strong>Email:</strong>

<?php
echo esc_html(
$registration->email
);
?>

</div>

<div>

<strong>Total Amount:</strong>

<?php
echo esc_html(
number_format(
floatval($registration->total_amount ?? 0),
2
)
);
?>

</div>

<div>

<strong>Payment Status:</strong>

<?php
echo esc_html(
    ucfirst($registration->payment_status ?? 'unpaid')
);
?>

</div>

<?php if (!empty($registration->paystack_reference)) : ?>

<div>

<strong>Paystack Reference:</strong>

<?php echo esc_html($registration->paystack_reference); ?>

</div>

<?php endif; ?>

<?php if (!empty($registration->paystack_authorization_url)) : ?>

<div>

<strong>Payment Link:</strong>

<a
href="<?php echo esc_url($registration->paystack_authorization_url); ?>"
target="_blank"
rel="noopener noreferrer"
>
Open Paystack Checkout
</a>

</div>

<?php endif; ?>

<?php if (!empty($registration->paid_at)) : ?>

<div>

<strong>Paid At:</strong>

<?php echo esc_html($registration->paid_at); ?>

</div>

<?php endif; ?>

<?php if (!empty($registration->rejection_reason)) : ?>

<div>

<strong>Rejection Reason:</strong>

<?php
echo esc_html(
$registration->rejection_reason
);
?>

</div>

<?php endif; ?>

<div>

<strong>Date Submitted:</strong>

<?php
echo esc_html(
$registration->created_at
);
?>

</div>

<?php if (!empty($registration->approved_at)) : ?>

<div>

<strong>Approved At:</strong>

<?php
echo esc_html(
$registration->approved_at
);
?>

</div>

<?php endif; ?>

</div>

<h2>

Submitted Information

</h2>

<div class="disi-data-grid">

<?php

if (!empty($data)) :

foreach ($data as $key => $value) :

if (
DISI_Registration_Manager::is_hidden_submission_field($key)
) {
continue;
}

if (is_array($value)) {
$value = implode(', ', $value);
}

?>

<div class="disi-field">

<div class="disi-label">

<?php

echo esc_html(

ucwords(
str_replace(
'_',
' ',
$key
)
)

);

?>

</div>

<div class="disi-value">

<?php
echo esc_html(
$value
);
?>

</div>

</div>

<?php

endforeach;

endif;

?>

</div>

<div class="disi-actions">

<?php if ($registration->status === 'pending') : ?>

    <form
    method="post"
    style="display:block;width:100%;margin-bottom:20px;"
    >

        <?php wp_nonce_field('disi_approve_' . $registration->id); ?>

        <?php if ($registration->registration_type === 'group_booking') : ?>

            <p>
                <label for="disi-group-custom-amount">
                    <strong>Group booking custom amount</strong>
                </label>
            </p>

            <input
            id="disi-group-custom-amount"
            name="group_custom_amount"
            type="text"
            inputmode="decimal"
            value="<?php echo esc_attr(
                number_format(
                    floatval($registration->registration_amount ?? 0),
                    2,
                    '.',
                    ','
                )
            ); ?>"
            required
            >

            <p class="description">
                Enter the total group registration amount before any workshop add-on.
            </p>

        <?php endif; ?>

        <p>
            <button
            type="submit"
            name="disi_approve_registration"
            class="button disi-approve-btn"
            >
            Approve and Generate Payment Link
            </button>
        </p>

    </form>

    <form
    method="post"
    style="display:block;width:100%;margin-top:16px;"
    >

        <?php
        wp_nonce_field(
            'disi_reject_' .
            $registration->id
        );
        ?>

        <p>
            <label for="disi-rejection-reason">
                <strong>Reason for rejection</strong>
            </label>
        </p>

        <textarea
        id="disi-rejection-reason"
        name="rejection_reason"
        rows="4"
        style="width:100%;max-width:640px;"
        required
        ></textarea>

        <p>
            <button
            type="submit"
            name="disi_reject_registration"
            class="button disi-reject-btn"
            >
            Reject Registration
            </button>
        </p>

    </form>

<?php endif; ?>

<?php if (
    $registration->status === 'approved' &&
    ($registration->payment_status ?? 'unpaid') !== 'paid' &&
    !empty($registration->paystack_reference)
) : ?>

    <form method="post" style="display:inline-block;">
        <?php
        wp_nonce_field(
            'disi_verify_payment_' . $registration->id
        );
        ?>
        <input
        type="hidden"
        name="paystack_reference"
        value="<?php echo esc_attr($registration->paystack_reference); ?>"
        >
        <button
        type="submit"
        name="disi_verify_payment"
        class="button"
        >
        Verify Payment
        </button>
    </form>

<?php endif; ?>

<a
href="<?php echo admin_url(
'admin.php?page=disi-registrations'
); ?>"
class="button"
>

Back

</a>

</div>

</div>

</div>
