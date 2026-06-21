<?php

if (!defined('ABSPATH')) {
    exit;
}

$existing_config = DISI_Settings::get_configuration();

if (
    isset($_POST['disi_save_configuration']) &&
    check_admin_referer(
        'disi_save_configuration_action'
    )
) {

    $config = [

        'provider' => sanitize_text_field(
            $_POST['provider'] ?? ''
        ),

        'participant_form' => intval(
            $_POST['participant_form'] ?? 0
        ),

        'paystack_secret_key' => !empty($_POST['paystack_secret_key'])
            ? sanitize_text_field(
                wp_unslash($_POST['paystack_secret_key'])
            )
            : ($existing_config['paystack_secret_key'] ?? ''),

        'paystack_public_key' => sanitize_text_field(
            wp_unslash($_POST['paystack_public_key'] ?? '')
        ),

        'paystack_callback_url' => esc_url_raw(
            wp_unslash($_POST['paystack_callback_url'] ?? '')
        ),

        'paystack_mode' => in_array(
            $_POST['paystack_mode'] ?? 'test',
            ['test', 'live'],
            true
        )
            ? sanitize_text_field($_POST['paystack_mode'])
            : 'test',

        'professional_amount' =>
            DISI_Registration_Manager::normalize_amount(
                $_POST['professional_amount'] ?? ''
            ),

        'academic_amount' =>
            DISI_Registration_Manager::normalize_amount(
                $_POST['academic_amount'] ?? ''
            ),

        'student_amount' =>
            DISI_Registration_Manager::normalize_amount(
                $_POST['student_amount'] ?? ''
            ),

        'group_booking_amount' =>
            DISI_Registration_Manager::normalize_amount(
                $_POST['group_booking_amount'] ?? ''
            ),

        'workshop_amount' =>
            DISI_Registration_Manager::normalize_amount(
                $_POST['workshop_amount'] ?? ''
            )

    ];

    DISI_Settings::save_configuration(
        $config
    );

    echo '
    <div class="notice notice-success">
        <p>Configuration saved successfully.</p>
    </div>
    ';
}

$config = DISI_Settings::get_configuration();

$providers =
DISI_Form_Provider::get_available_providers();

$provider =
$_POST['provider']
?? $config['provider']
?? '';

$forms = [];

if (!empty($provider)) {

    $forms =
    DISI_Form_Provider::get_forms(
        $provider
    );
}

$amount_fields = [
    'professional_amount' => 'Professional Amount',
    'academic_amount' => 'Academic/Researcher Amount',
    'student_amount' => 'Student Amount',
    'group_booking_amount' => 'Group Booking Amount Per Person',
    'workshop_amount' => 'Workshop Payment Amount'
];

?>

<div class="wrap">

    <h1>DISI Portal Integrations</h1>

    <form method="post">

        <?php
        wp_nonce_field(
            'disi_save_configuration_action'
        );
        ?>

        <table class="form-table">

            <tr>

                <th>
                    Form Provider
                </th>

                <td>

                    <select
                        name="provider"
                        onchange="this.form.submit();"
                    >

                        <option value="">
                            Select Provider
                        </option>

                        <?php foreach ($providers as $value => $label) : ?>

                            <option
                                value="<?php echo esc_attr($value); ?>"
                                <?php selected($provider, $value); ?>
                            >
                                <?php echo esc_html($label); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <p class="description">

                        Choose from the supported form plugins currently installed.

                    </p>

                </td>

            </tr>

            <tr>

                <th>
                    Registration Form
                </th>

                <td>

                    <select
                        name="participant_form"
                    >

                        <option value="">
                            Select Form
                        </option>

                        <?php foreach ($forms as $form) : ?>

                            <?php
                            $form_id = DISI_Form_Provider::get_form_id($form);
                            $form_title = DISI_Form_Provider::get_form_title($form);
                            ?>

                            <option
                                value="<?php echo esc_attr($form_id); ?>"
                                <?php selected(
                                    $config['participant_form'] ?? '',
                                    $form_id
                                ); ?>
                            >
                                <?php echo esc_html($form_title); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </td>

            </tr>

            <tr>
                <th>Paystack Mode</th>
                <td>
                    <select name="paystack_mode">
                        <option
                            value="test"
                            <?php selected($config['paystack_mode'] ?? 'test', 'test'); ?>
                        >
                            Test
                        </option>
                        <option
                            value="live"
                            <?php selected($config['paystack_mode'] ?? 'test', 'live'); ?>
                        >
                            Live
                        </option>
                    </select>
                    <p class="description">
                        The selected mode must match the configured API keys.
                    </p>
                </td>
            </tr>

            <tr>
                <th>Paystack Secret Key</th>
                <td>
                    <input
                        type="password"
                        name="paystack_secret_key"
                        class="regular-text"
                        value=""
                        autocomplete="new-password"
                        placeholder="<?php
                        echo !empty($config['paystack_secret_key'])
                            ? 'Saved - leave blank to keep current key'
                            : 'sk_test_...';
                        ?>"
                    >
                    <p class="description">
                        Used securely on the server to initialize and verify payments.
                        The saved key is never displayed.
                    </p>
                </td>
            </tr>

            <tr>
                <th>Paystack Public Key</th>
                <td>
                    <input
                        type="text"
                        name="paystack_public_key"
                        class="regular-text"
                        value="<?php echo esc_attr($config['paystack_public_key'] ?? ''); ?>"
                        placeholder="pk_test_..."
                    >
                </td>
            </tr>

            <tr>
                <th>Callback / Thank-you URL</th>
                <td>
                    <input
                        type="url"
                        name="paystack_callback_url"
                        class="regular-text"
                        value="<?php echo esc_attr($config['paystack_callback_url'] ?? ''); ?>"
                        placeholder="<?php echo esc_attr(home_url('/')); ?>"
                    >
                    <p class="description">
                        After server-side verification, the participant is redirected here.
                    </p>
                </td>
            </tr>

            <?php foreach ($amount_fields as $field => $label) : ?>

                <tr>

                    <th>
                        <?php echo esc_html($label); ?>
                    </th>

                    <td>

                        <input
                            type="text"
                            name="<?php echo esc_attr($field); ?>"
                            class="regular-text disi-amount-input"
                            inputmode="decimal"
                            value="<?php
                            $amount = DISI_Registration_Manager::normalize_amount(
                                $config[$field] ?? ''
                            );
                            echo esc_attr(
                                $amount > 0
                                    ? number_format($amount, 2, '.', ',')
                                    : ''
                            );
                            ?>"
                            placeholder="<?php
                            echo esc_attr(
                                $field === 'workshop_amount'
                                ? 'The workshop payment is an add-on to the registration type amount'
                                : 'Example: 50,000.00'
                            );
                            ?>"
                        >

                        <?php if ($field === 'workshop_amount') : ?>

                            <p class="description">

                                The workshop payment is an add-on to the selected
                                registration type amount for subsequent usage.

                            </p>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

        <p>

            <button
                type="submit"
                name="disi_save_configuration"
                class="button button-primary"
            >
                Save Configuration
            </button>

        </p>

    </form>

</div>

<script>
document.querySelectorAll('.disi-amount-input').forEach(function (input) {
    input.addEventListener('input', function () {
        var parts = input.value.replace(/,/g, '').replace(/[^\d.]/g, '').split('.');
        var whole = parts.shift() || '';
        var decimal = parts.join('').slice(0, 2);

        input.value = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') +
            (parts.length ? '.' + decimal : '');
    });
});
</script>
