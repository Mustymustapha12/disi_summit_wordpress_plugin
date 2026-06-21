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

?>

<div class="wrap">

<h1>DISI Summit Portal V0.2.1</h1>

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

</div>

</div>
