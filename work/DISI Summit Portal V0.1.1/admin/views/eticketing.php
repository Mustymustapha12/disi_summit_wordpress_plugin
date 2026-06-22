<?php

if (!defined('ABSPATH')) {
    exit;
}

$search = sanitize_text_field($_GET['s'] ?? '');
$rows = DISI_Ticketing::get_eligible($search);
?>

<div class="wrap">

<h1 class="wp-heading-inline">E-ticketing</h1>

<hr class="wp-header-end">

<?php if (!empty($_GET['ticket_notice'])) : ?>

<div class="notice <?php
echo ($_GET['ticket_notice'] === 'sent')
    ? 'notice-success'
    : 'notice-error';
?> is-dismissible">
    <p><?php
    echo esc_html(
        sanitize_text_field(
            wp_unslash($_GET['ticket_message'] ?? '')
        )
    );
    ?></p>
</div>

<?php endif; ?>

<form method="get">
    <input type="hidden" name="page" value="disi-eticketing">
    <input
        type="search"
        name="s"
        placeholder="Search participant..."
        value="<?php echo esc_attr($search); ?>"
    >
    <button type="submit" class="button">Search</button>
</form>

<p class="description">
Approved registrations with verified successful payments appear here.
</p>

<table class="widefat striped disi-ticket-table">
    <thead>
        <tr>
            <th>S/N</th>
            <th>Ticket</th>
            <th>Participant</th>
            <th>Contact</th>
            <th>Type</th>
            <th>Issued</th>
            <th>Email Sent</th>
            <th>Scans</th>
            <th>Last Scan</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>

    <?php if (!empty($rows)) : ?>

        <?php foreach ($rows as $index => $row) : ?>
            <?php
            $name = trim(
                ($row->first_name ?? '') . ' ' .
                ($row->last_name ?? '')
            );
            $send_url = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => 'disi_send_ticket',
                        'registration_id' => $row->id
                    ],
                    admin_url('admin-post.php')
                ),
                'disi_send_ticket_' . $row->id
            );
            ?>
            <tr>
                <td><?php echo esc_html($index + 1); ?></td>
                <td>
                    <?php if (!empty($row->ticket_token)) : ?>
                        <strong><?php
                        echo esc_html(
                            DISI_Ticketing::ticket_number($row)
                        );
                        ?></strong>
                    <?php else : ?>
                        <span class="disi-payment-badge disi-payment-unpaid">
                            Not issued
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?php
                    echo esc_html($name ?: $row->email);
                    ?></strong>
                    <br>
                    <small><?php
                    echo esc_html(
                        DISI_Registration_Manager::get_registration_number(
                            $row
                        )
                    );
                    ?></small>
                </td>
                <td>
                    <?php echo esc_html($row->email); ?>
                    <br>
                    <?php echo esc_html($row->phone); ?>
                </td>
                <td><?php
                echo esc_html(
                    DISI_Registration_Manager::label_registration_type(
                        $row->registration_type
                    )
                );
                ?></td>
                <td><?php echo esc_html($row->ticket_issued_at ?: '-'); ?></td>
                <td><?php
                echo esc_html($row->ticket_email_sent_at ?: '-');
                ?></td>
                <td>
                    <span class="disi-scan-count"><?php
                    echo esc_html(intval($row->ticket_scan_count ?? 0));
                    ?></span>
                </td>
                <td><?php
                echo esc_html($row->ticket_last_scanned_at ?: 'Not scanned');
                ?></td>
                <td>
                    <a
                        href="<?php echo esc_url($send_url); ?>"
                        class="button button-small disi-email-btn"
                    >
                        <?php echo empty($row->ticket_token)
                            ? 'Issue & Email'
                            : 'Resend Ticket'; ?>
                    </a>

                    <?php if (!empty($row->ticket_token)) : ?>
                        <a
                            href="<?php echo esc_url(
                                DISI_Ticketing::ticket_url($row, true)
                            ); ?>"
                            class="button button-small"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            View E-ticket
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

    <?php else : ?>

        <tr>
            <td colspan="10">
                No approved and paid participants found.
            </td>
        </tr>

    <?php endif; ?>

    </tbody>
</table>

</div>
