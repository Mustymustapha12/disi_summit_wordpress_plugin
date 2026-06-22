<?php

if (!defined('ABSPATH')) {
    exit;
}

$page_number = max(1, intval($_GET['paged'] ?? 1));
$allowed_per_page = [10, 25, 50];
$per_page = intval($_GET['per_page'] ?? 10);

if (!in_array($per_page, $allowed_per_page, true)) {
    $per_page = 10;
}

$search = sanitize_text_field($_GET['s'] ?? '');
$type = sanitize_text_field($_GET['type'] ?? '');
$ticket_status = sanitize_text_field($_GET['ticket_status'] ?? '');
$scan_status = sanitize_text_field($_GET['scan_status'] ?? '');

$total = DISI_Ticketing::eligible_count(
    $search,
    $type,
    $ticket_status,
    $scan_status
);
$total_pages = max(1, intval(ceil($total / $per_page)));
$page_number = min($page_number, $total_pages);
$rows = DISI_Ticketing::get_eligible_paginated(
    $page_number,
    $per_page,
    $search,
    $type,
    $ticket_status,
    $scan_status
);
$serial_number = (($page_number - 1) * $per_page) + 1;

$filter_args = [
    'type' => $type,
    'ticket_status' => $ticket_status,
    'scan_status' => $scan_status,
    's' => $search
];
$export_args = array_merge(
    ['action' => 'disi_export_tickets'],
    $filter_args
);
$csv_url = wp_nonce_url(
    add_query_arg(
        array_merge($export_args, ['format' => 'csv']),
        admin_url('admin-post.php')
    ),
    'disi_export_tickets'
);
$pdf_url = wp_nonce_url(
    add_query_arg(
        array_merge($export_args, ['format' => 'pdf']),
        admin_url('admin-post.php')
    ),
    'disi_export_tickets'
);
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

<p class="description">
Approved registrations with verified successful payments appear here.
</p>

<form method="get" class="disi-ticket-filters">
    <input type="hidden" name="page" value="disi-eticketing">

    <select name="type">
        <option value="">All Types</option>
        <option value="professional" <?php
        selected($type, 'professional');
        ?>>Professional</option>
        <option value="academic_researcher" <?php
        selected($type, 'academic_researcher');
        ?>>Academic/Researcher</option>
        <option value="student" <?php
        selected($type, 'student');
        ?>>Student</option>
        <option value="group_booking" <?php
        selected($type, 'group_booking');
        ?>>Group Booking</option>
        <option value="workshop_only" <?php
        selected($type, 'workshop_only');
        ?>>Workshop Only</option>
    </select>

    <select name="ticket_status">
        <option value="">All Ticket Statuses</option>
        <option value="issued" <?php
        selected($ticket_status, 'issued');
        ?>>Issued</option>
        <option value="not_issued" <?php
        selected($ticket_status, 'not_issued');
        ?>>Not Issued</option>
    </select>

    <select name="scan_status">
        <option value="">All Scan Statuses</option>
        <option value="scanned" <?php
        selected($scan_status, 'scanned');
        ?>>Scanned</option>
        <option value="not_scanned" <?php
        selected($scan_status, 'not_scanned');
        ?>>Not Scanned</option>
    </select>

    <input
        type="search"
        name="s"
        placeholder="Search participant..."
        value="<?php echo esc_attr($search); ?>"
    >

    <select name="per_page" aria-label="Rows per page">
        <?php foreach ($allowed_per_page as $option) : ?>
            <option value="<?php echo esc_attr($option); ?>" <?php
            selected($per_page, $option);
            ?>>
                <?php echo esc_html($option); ?> per page
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Filter</button>

    <?php if (
        $search ||
        $type ||
        $ticket_status ||
        $scan_status ||
        $per_page !== 10
    ) : ?>
        <a
            class="button"
            href="<?php echo esc_url(
                admin_url('admin.php?page=disi-eticketing')
            ); ?>"
        >
            Clear
        </a>
    <?php endif; ?>
</form>

<div class="disi-ticket-toolbar">
    <div class="disi-export-actions">
        <a class="button" href="<?php echo esc_url($csv_url); ?>">
            Export CSV
        </a>
        <a class="button" href="<?php echo esc_url($pdf_url); ?>">
            Export PDF
        </a>
    </div>
    <div class="disi-ticket-total">
        <?php echo esc_html(number_format($total)); ?>
        participant<?php echo $total === 1 ? '' : 's'; ?>
    </div>
</div>

<div class="disi-ticket-table-wrap">
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

        <?php foreach ($rows as $row) : ?>
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
                <td><?php echo esc_html($serial_number++); ?></td>
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
                <td><?php
                echo esc_html($row->ticket_issued_at ?: '-');
                ?></td>
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
                No approved and paid participants matched these filters.
            </td>
        </tr>

    <?php endif; ?>

    </tbody>
</table>
</div>

<?php if ($total_pages > 1) : ?>
    <?php
    $pagination_base = str_replace(
        '999999999',
        '%#%',
        add_query_arg(
        array_merge(
            [
                'page' => 'disi-eticketing',
                'paged' => 999999999,
                'per_page' => $per_page
            ],
            $filter_args
        ),
        admin_url('admin.php')
        )
    );
    ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php echo wp_kses_post(
                paginate_links([
                    'base' => $pagination_base,
                    'format' => '',
                    'current' => $page_number,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ])
            ); ?>
        </div>
    </div>
<?php endif; ?>

</div>
