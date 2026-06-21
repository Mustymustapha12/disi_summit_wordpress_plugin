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

$search = sanitize_text_field(
    $_GET['s'] ?? ''
);

$rows =
DISI_Registration_Manager::get_paginated(
    $page,
    $per_page,
    $type,
    $status,
    $search
);

$total =
DISI_Registration_Manager::total_count(
    $type,
    $status,
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
echo esc_html(
    ucfirst($row->payment_status ?? 'unpaid')
);
?>

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

<td colspan="8">

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
