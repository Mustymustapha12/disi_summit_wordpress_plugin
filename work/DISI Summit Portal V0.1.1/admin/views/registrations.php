<?php

if (!defined('ABSPATH')) {
    exit;
}

$page = max(
    1,
    intval($_GET['paged'] ?? 1)
);

$per_page = 20;

$type = sanitize_text_field(
    $_GET['type'] ?? ''
);

$status = sanitize_text_field(
    $_GET['status'] ?? ''
);

$payment_status = sanitize_text_field(
    $_GET['payment_status'] ?? ''
);

$search = sanitize_text_field(
    $_GET['s'] ?? ''
);

$rows =
DISI_Registration_Manager::get_paginated(
    $page,
    $per_page,
    $type,
    $status,
    $payment_status,
    $search
);

$total =
DISI_Registration_Manager::total_count(
    $type,
    $status,
    $payment_status,
    $search
);

$total_pages =
ceil($total / $per_page);

$sn =
(($page - 1) * $per_page) + 1;

?>

<div class="wrap">

<h1 class="wp-heading-inline">
Registrations
</h1>

<hr class="wp-header-end">

<form method="get">

<input
type="hidden"
name="page"
value="disi-registrations"
>

<select name="type">

<option value="">
All Types
</option>

<option
value="professional"
<?php selected($type, 'professional'); ?>
>
Professional
</option>

<option
value="academic_researcher"
<?php selected($type, 'academic_researcher'); ?>
>
Academic/Researcher
</option>

<option
value="student"
<?php selected($type, 'student'); ?>
>
Student
</option>

<option
value="group_booking"
<?php selected($type, 'group_booking'); ?>
>
Group Booking
</option>

<option
value="workshop_only"
<?php selected($type, 'workshop_only'); ?>
>
Workshop Only
</option>

</select>

<select name="status">

<option value="">
All Status
</option>

<option
value="pending"
<?php selected(
$status,
'pending'
); ?>
>
Pending
</option>

<option
value="approved"
<?php selected(
$status,
'approved'
); ?>
>
Approved
</option>

<option
value="rejected"
<?php selected(
$status,
'rejected'
); ?>
>
Rejected
</option>

</select>

<select name="payment_status">

<option value="">
All Payments
</option>

<option
value="unpaid"
<?php selected($payment_status, 'unpaid'); ?>
>
Unpaid
</option>

<option
value="paid"
<?php selected($payment_status, 'paid'); ?>
>
Paid
</option>

</select>

<input
type="search"
name="s"
placeholder="Search..."
value="<?php echo esc_attr($search); ?>"
>

<button
class="button"
type="submit"
>
Filter
</button>

</form>

<?php
$export_args = [
    'action' => 'disi_export_registrations',
    'type' => $type,
    'status' => $status,
    'payment_status' => $payment_status,
    's' => $search
];

$csv_url = wp_nonce_url(
    add_query_arg(
        array_merge($export_args, ['format' => 'csv']),
        admin_url('admin-post.php')
    ),
    'disi_export_registrations'
);

$pdf_url = wp_nonce_url(
    add_query_arg(
        array_merge($export_args, ['format' => 'pdf']),
        admin_url('admin-post.php')
    ),
    'disi_export_registrations'
);
?>

<div class="disi-export-actions">
    <a class="button" href="<?php echo esc_url($csv_url); ?>">
        Export CSV
    </a>
    <a class="button" href="<?php echo esc_url($pdf_url); ?>">
        Export PDF
    </a>
</div>

<br>

<table class="widefat striped">

<thead>

<tr>

<th>S/N</th>
<th>Type</th>
<th>Name</th>
<th>Email</th>
<th>Status</th>
<th>Payment</th>
<th>Amount</th>
<th>Date</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php if (!empty($rows)) : ?>

<?php foreach ($rows as $row) : ?>

<tr>

<td>
<?php echo esc_html($sn++); ?>
</td>

<td>
<?php echo esc_html(
DISI_Registration_Manager::label_registration_type(
    $row->registration_type
)
); ?>
</td>

<td>

<?php

$name = trim(
$row->first_name .
' ' .
$row->last_name
);

if (empty($name)) {
$name = $row->business_name;
}

if (empty($name)) {
$name = $row->email;
}

echo esc_html($name);

?>

</td>

<td>
<?php echo esc_html(
$row->email
); ?>
</td>

<td>

<?php

$class = '';

switch ($row->status) {

case 'approved':
$class = 'disi-approved';
break;

case 'rejected':
$class = 'disi-rejected';
break;

default:
$class = 'disi-pending';
}

?>

<span
class="<?php echo esc_attr($class); ?>"
>

<?php
echo esc_html(
ucfirst(
$row->status
)
);
?>

</span>

</td>

<td>

<?php
$row_payment_status = $row->payment_status ?? 'unpaid';
?>

<span
class="disi-payment-badge disi-payment-<?php echo esc_attr($row_payment_status); ?>"
>
<?php echo esc_html(ucfirst($row_payment_status)); ?>
</span>

</td>

<td>

<span class="disi-money">
&#8358;<?php
echo esc_html(
    number_format(
        floatval($row->total_amount ?? 0),
        2
    )
);
?>
</span>

</td>

<td>

<?php
echo esc_html(
$row->created_at
);
?>

</td>

<td>

<a
class="button button-small disi-view-btn"
href="<?php echo admin_url(
'admin.php?page=disi-registration-view&id=' .
$row->id
); ?>"
>
View
</a>

</td>

</tr>

<?php endforeach; ?>

<?php else : ?>

<tr>

<td colspan="9">

No registrations found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

<?php if ($total_pages > 1) : ?>

<div
style="
margin-top:20px;
"
>

<?php

for (
$i = 1;
$i <= $total_pages;
$i++
) :

?>

<a

class="button <?php

echo
$page === $i
? 'button-primary'
: '';

?>"

href="<?php

echo esc_url(

add_query_arg(

[
'page' => 'disi-registrations',
'paged' => $i,
'type' => $type,
'status' => $status,
'payment_status' => $payment_status,
's' => $search
]

)

);

?>"

>

<?php echo $i; ?>

</a>

<?php endfor; ?>

</div>

<?php endif; ?>

</div>
