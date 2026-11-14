<?php
/*
 * Bhelpdesk - English (base language)
 *
 * This is the reference file: every key used by the application MUST exist here.
 * To add a language copy this file to lang/<code>.php (e.g. de.php, fr.php),
 * translate the values and leave the keys untouched.
 * Missing keys in other languages automatically fall back to English.
 *
 * Placeholders like :name, :date, :number are replaced at runtime: keep them.
 */

return [
    // ---- Language metadata ----
    'lang.name'        => 'English',
    'lang.label'       => 'Language',
    'date_format'      => 'Y-m-d H:i',

    // ---- Layout / navigation ----
    'app.operators_area' => 'Operators',
    'nav.open_ticket'    => 'Open a ticket',
    'nav.check_status'   => 'Check status',
    'nav.tickets'        => 'Tickets',
    'nav.my_tickets'     => 'My tickets',
    'nav.operators'      => 'Operators',
    'nav.logout'         => 'Log out',

    // ---- Generic errors ----
    'error.session_expired.title'   => 'Session expired',
    'error.session_expired.message' => 'The form has expired. Go back, reload the page and try again.',
    'error.not_found.title'         => 'Page not found',
    'error.not_found.message'       => 'The requested page does not exist.',
    'error.forbidden.title'         => 'Access denied',
    'error.forbidden.message'       => 'This feature is reserved for administrators.',
    'error.ip_denied'               => 'Access not allowed.',
    'error.ticket_not_found.title'  => 'Ticket not found',
    'error.ticket_not_found.message'=> 'The requested ticket does not exist.',
    'error.back_home'               => 'Back to start',

    // ---- Ticket status / priority ----
    'status.open'      => 'Open',
    'status.answered'  => 'Answered',
    'status.on_hold'   => 'On hold',
    'status.closed'    => 'Closed',
    'priority.low'     => 'Low',
    'priority.normal'  => 'Normal',
    'priority.high'    => 'High',
    'priority.urgent'  => 'Urgent',

    // ---- Customer: home ----
    'home.title'        => 'Support',
    'home.heading'      => 'Help center',
    'home.intro'        => 'Open a support request or check the status of an existing ticket.',
    'home.new.title'    => 'Open a new ticket',
    'home.new.text'     => 'Describe the problem: you will get a ticket number to follow its progress.',
    'home.status.title' => 'Check status',
    'home.status.text'  => 'Enter ticket number and email to see replies and status.',

    // ---- Customer: new ticket ----
    'new.title'       => 'Open a ticket',
    'new.name'        => 'Full name',
    'new.email'       => 'Email',
    'new.department'  => 'Department',
    'new.subject'     => 'Subject',
    'new.message'     => 'Describe the problem',
    'new.submit'      => 'Submit request',
    'new.honeypot'    => 'Website',

    'val.invalid_request'   => 'Invalid request.',
    'val.name_required'     => 'Please enter your name.',
    'val.email_invalid'     => 'Invalid email address.',
    'val.subject_required'  => 'Please enter a subject (max 255 characters).',
    'val.message_min'       => 'Please describe the problem (at least 10 characters).',
    'val.too_many_tickets'  => 'You have opened too many tickets in a short time. Please try again later.',

    // ---- Customer: ticket created ----
    'created.title'  => 'Ticket opened',
    'created.intro'  => 'Your request has been registered with number:',
    'created.keep'   => 'Keep this number: together with your email you can check the status at any time. Alternatively save this direct access link (do not share it):',
    'created.go'     => 'Go to the ticket',

    // ---- Customer: status lookup ----
    'lookup.title'        => 'Check ticket status',
    'lookup.number'       => 'Ticket number',
    'lookup.placeholder'  => 'e.g. 482913',
    'lookup.email'        => 'Email used when opening the ticket',
    'lookup.submit'       => 'Check',
    'lookup.too_many'     => 'Too many attempts. Please try again in a few minutes.',
    'lookup.not_found'    => 'No ticket found with these details.',
    'lookup.need_login'   => 'To view the ticket enter its number and your email.',

    // ---- Customer: ticket view ----
    'ticket.title'         => 'Ticket #:number',
    'ticket.opened_on'     => 'Opened on :date',
    'ticket.last_update'   => 'Last update :date',
    'ticket.support'       => 'Support',
    'ticket.reply'         => 'Reply',
    'ticket.reply_reopen'  => 'Reply (the ticket will be reopened)',
    'ticket.send'          => 'Send',
    'ticket.closed_info'   => 'This ticket is closed.',
    'ticket.closed_new'    => 'Open a new ticket',
    'ticket.leave'         => 'Leave this ticket',
    'ticket.closed_no_reply' => 'The ticket is closed: please open a new one.',
    'ticket.empty_message' => 'The message is empty.',
    'ticket.reply_sent'    => 'Reply sent.',

    // ---- Operators: login ----
    'login.title'     => 'Operator login',
    'login.username'  => 'Username',
    'login.password'  => 'Password',
    'login.submit'    => 'Sign in',
    'login.too_many'  => 'Too many failed attempts. Please try again later.',
    'login.invalid'   => 'Invalid credentials.',

    // ---- Operators: dashboard ----
    'dash.title'           => 'Tickets',
    'dash.active'          => 'Active',
    'dash.all'             => 'All',
    'dash.search'          => 'Number, subject, email, name…',
    'dash.all_operators'   => 'All operators',
    'dash.assigned_me'     => 'Assigned to me',
    'dash.unassigned'      => 'Unassigned',
    'dash.all_departments' => 'All departments',
    'dash.filter'          => 'Filter',
    'dash.empty'           => 'No tickets.',

    'col.number'      => 'Number',
    'col.subject'     => 'Subject',
    'col.customer'    => 'Customer',
    'col.department'  => 'Department',
    'col.priority'    => 'Priority',
    'col.status'      => 'Status',
    'col.assignee'    => 'Assigned to',
    'col.updated'     => 'Updated',

    // ---- Operators: ticket ----
    'opticket.opened'           => 'opened on :date from :ip',
    'opticket.operator'         => 'Operator',
    'opticket.internal_badge'   => 'internal note',
    'opticket.reply_customer'   => 'Reply to customer',
    'opticket.status_after'     => 'Status after sending',
    'opticket.send_reply'       => 'Send reply',
    'opticket.internal_title'   => 'Internal note',
    'opticket.internal_hint'    => 'Visible to operators only',
    'opticket.add_note'         => 'Add note',
    'opticket.manage'           => 'Manage',
    'opticket.nobody'           => '— nobody —',
    'opticket.save'             => 'Save',
    'opticket.created'          => 'Created',
    'opticket.updated_at'       => 'Updated',
    'opticket.closed_at'        => 'Closed',
    'opticket.note_added'       => 'Internal note added.',
    'opticket.reply_sent'       => 'Reply sent.',
    'opticket.saved'            => 'Ticket updated.',

    // ---- System log inside tickets ----
    'log.changed'     => ':who changed :changes',
    'log.status'      => 'status: :from → :to',
    'log.priority'    => 'priority: :from → :to',
    'log.department'  => 'department: :name',
    'log.assigned'    => 'assigned to: :name',
    'log.none'        => 'none',
    'log.separator'   => ', ',
    'log.reopened'    => 'Ticket reopened by the customer',

    // ---- Operators: management ----
    'ops.title'          => 'Operators',
    'ops.username'       => 'Username',
    'ops.name'           => 'Name',
    'ops.email'          => 'Email',
    'ops.role'           => 'Role',
    'ops.last_login'     => 'Last login',
    'ops.state'          => 'Status',
    'ops.active'         => 'Active',
    'ops.disabled'       => 'Disabled',
    'ops.disable'        => 'Disable',
    'ops.enable'         => 'Re-enable',
    'ops.new'            => 'New operator',
    'ops.password'       => 'Password (min. 12 characters)',
    'ops.create'         => 'Create operator',
    'ops.created'        => 'Operator created.',
    'ops.self_disable'   => 'You cannot disable yourself.',
    'ops.state_updated'  => 'Operator status updated.',
    'role.admin'         => 'Administrator',
    'role.agent'         => 'Agent',

    'val.username'          => 'Username: 3-50 characters, letters, numbers, . _ -',
    'val.ops_name_required' => 'Name is required.',
    'val.password_short'    => 'The password must be at least 12 characters long.',
    'val.duplicate'         => 'Username or email already in use.',

    // ---- Command line (bin/create-operator.php) ----
    'cli.username'        => 'Username',
    'cli.name'            => 'Full name',
    'cli.email'           => 'Email',
    'cli.password'        => 'Password (min 12 characters)',
    'cli.confirm'         => 'Repeat password',
    'cli.error'           => 'Error: :errors',
    'cli.err_username'    => 'invalid username',
    'cli.err_name'        => 'name is required',
    'cli.err_email'       => 'invalid email',
    'cli.err_password'    => 'password too short',
    'cli.err_mismatch'    => 'passwords do not match',
    'cli.db_error'        => 'Database error: :error',
    'cli.created'         => 'Operator #:id (:role) created.',
];
