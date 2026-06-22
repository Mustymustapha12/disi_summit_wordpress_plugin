<?php

if (!defined('ABSPATH')) {
    exit;
}

$pending =
DISI_Registration_Manager::total_count(
    '',
    'pending'
);

$approved =
DISI_Registration_Manager::total_count(
    '',
    'approved'
);

$rejected =
DISI_Registration_Manager::total_count(
    '',
    'rejected'
);

$total =
$pending +
$approved +
$rejected;

$paid =
DISI_Registration_Manager::payment_count(
    'paid'
);

$amounts =
DISI_Registration_Manager::amount_totals();

?>

<div class="wrap">

<h1>DISI Summit Portal V0.3.4</h1>

<div class="disi-dashboard-grid">

    <div class="disi-dashboard-card disi-dashboard-card-primary">

        <h3>Total Registrations</h3>

        <div class="count">

            <?php echo esc_html($total); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-pending">

        <h3>Pending Registrations</h3>

        <div class="count">

            <?php echo esc_html($pending); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-approved">

        <h3>Approved Registrations</h3>

        <div class="count">

            <?php echo esc_html($approved); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-rejected">

        <h3>Rejected Registrations</h3>

        <div class="count">

            <?php echo esc_html($rejected); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-approved">

        <h3>Paid Registrations</h3>

        <div class="count">

            <?php echo esc_html($paid); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-primary">

        <h3>Active Registration Fees</h3>

        <div class="count disi-dashboard-amount">

            &#8358;<?php echo esc_html(
                number_format(
                    floatval($amounts->registration_amount ?? 0),
                    1
                )
            ); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-pending">

        <h3>Active Workshop Add-ons</h3>

        <div class="count disi-dashboard-amount">

            &#8358;<?php echo esc_html(
                number_format(
                    floatval($amounts->workshop_amount ?? 0),
                    1
                )
            ); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-primary">

        <h3>Active Expected Amount</h3>

        <div class="count disi-dashboard-amount">

            &#8358;<?php echo esc_html(
                number_format(
                    floatval($amounts->total_amount ?? 0),
                    1
                )
            ); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-approved">

        <h3>Verified Amount Paid</h3>

        <div class="count disi-dashboard-amount">

            &#8358;<?php echo esc_html(
                number_format(
                    floatval($amounts->paid_amount ?? 0),
                    1
                )
            ); ?>

        </div>

    </div>

    <div class="disi-dashboard-card disi-dashboard-card-rejected">

        <h3>Rejected Registration Amount</h3>

        <div class="count disi-dashboard-amount">

            &#8358;<?php echo esc_html(
                number_format(
                    floatval($amounts->rejected_amount ?? 0),
                    1
                )
            ); ?>

        </div>

    </div>

</div>

</div>
