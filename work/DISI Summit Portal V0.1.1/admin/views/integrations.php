<?php

if (!defined('ABSPATH')) {
    exit;
}

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

        'paystack_link' => esc_url_raw(
            $_POST['paystack_link'] ?? ''
        ),

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

                <th>
                    Paystack Payment Link
                </th>

                <td>

                    <input
                        type="url"
                        name="paystack_link"
                        class="regular-text"
                        value="<?php echo esc_attr($config['paystack_link'] ?? ''); ?>"
                        placeholder="https://paystack.com/pay/..."
                    >

                    <p class="description">

                        This link is sent to approved registrants.

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
