<?php

if (!defined('ABSPATH')) {
    exit;
}

$notice = '';
$notice_class = '';

if (
    isset($_POST['disi_activate_license']) &&
    check_admin_referer('disi_activate_license_action')
) {
    $result = DISI_License::activate(
        wp_unslash($_POST['disi_license_key'] ?? '')
    );

    if (is_wp_error($result)) {
        $notice = $result->get_error_message();
        $notice_class = 'notice-error';
    } else {
        $notice = 'This WordPress installation has been approved successfully.';
        $notice_class = 'notice-success';
    }
}

$is_active = DISI_License::is_active();
?>

<div class="wrap disi-license-page">
    <h1>DISI Portal License</h1>

    <?php if ($notice) : ?>
        <div class="notice <?php echo esc_attr($notice_class); ?>">
            <p><?php echo esc_html($notice); ?></p>
        </div>
    <?php endif; ?>

    <div class="disi-license-status <?php
    echo $is_active ? 'is-active' : 'is-inactive';
    ?>">
        <strong><?php
        echo $is_active ? 'Approved' : 'Approval Required';
        ?></strong>
        <span><?php
        echo esc_html(DISI_License::status_message());
        ?></span>
    </div>

    <?php if (!$is_active) : ?>
        <p>
            Send the request code below to the plugin owner. Paste the
            activation key you receive into the activation field.
        </p>

        <table class="form-table">
            <tr>
                <th>Approved Site URL</th>
                <td><code><?php
                echo esc_html(DISI_License::site_identity());
                ?></code></td>
            </tr>
            <tr>
                <th>Request Code</th>
                <td>
                    <textarea
                        class="large-text code"
                        rows="4"
                        readonly
                        onclick="this.select();"
                    ><?php
                    echo esc_textarea(DISI_License::request_code());
                    ?></textarea>
                </td>
            </tr>
        </table>

        <form method="post">
            <?php wp_nonce_field('disi_activate_license_action'); ?>

            <table class="form-table">
                <tr>
                    <th>Activation Key</th>
                    <td>
                        <textarea
                            name="disi_license_key"
                            class="large-text code"
                            rows="6"
                            required
                            placeholder="DISI-LIC-..."
                        ></textarea>
                    </td>
                </tr>
            </table>

            <p>
                <button
                    type="submit"
                    name="disi_activate_license"
                    class="button button-primary"
                >
                    Approve This Installation
                </button>
            </p>
        </form>
    <?php else : ?>
        <p>
            The portal is fully enabled for
            <code><?php echo esc_html(DISI_License::site_identity()); ?></code>.
        </p>
    <?php endif; ?>
</div>
