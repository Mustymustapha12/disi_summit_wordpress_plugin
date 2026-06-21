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
$action = sanitize_text_field($_GET['action'] ?? '');
$nonce = sanitize_text_field(
    wp_unslash($_GET['_wpnonce'] ?? '')
);

if (
    $action === 'approve' &&
    wp_verify_nonce($nonce, 'disi_approve_' . $id)
) {
    DISI_Registration_Manager::approve($id);

    echo '
    <div class="notice notice-success">
    <p>Registration approved successfully.</p>
    </div>
    ';
}

if (
    $action === 'delete' &&
    wp_verify_nonce($nonce, 'disi_delete_' . $id)
) {
    DISI_Registration_Manager::delete($id);

    echo '
    <div class="notice notice-success">
        <p>
            Registration deleted successfully.
            <a href="' .
            esc_url(admin_url('admin.php?page=disi-registrations')) .
            '">Return to registrations</a>.
        </p>
    </div>
    ';
    return;
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

    <a
    href="<?php

    echo wp_nonce_url(

        admin_url(

            'admin.php?page=disi-registration-view&id=' .
            $registration->id .
            '&action=approve'

        ),

        'disi_approve_' .
        $registration->id

    );

    ?>"
    class="button disi-approve-btn"
    >

    Approve Registration

    </a>

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

<?php if ($registration->status !== 'pending') : ?>


<?php endif; ?>



<a
href="<?php echo admin_url(
'admin.php?page=disi-registrations'
); ?>"
class="button"
>

Back

</a>

<a
href="<?php
echo esc_url(
    wp_nonce_url(
        admin_url(
            'admin.php?page=disi-registration-view&id=' .
            $registration->id .
            '&action=delete'
        ),
        'disi_delete_' . $registration->id
    )
);
?>"
class="button button-link-delete"
onclick="return confirm('Permanently delete this registration from the DISI Portal?');"
>
Delete Registration
</a>

</div>

</div>

</div>
