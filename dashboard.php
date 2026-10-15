
<?php
session_start();

// =============================================
// LOGIN PROTECTION
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// =============================================
// ORGANIZATION INFORMATION
// =============================================
$org = [
    'name' => 'My Accounting Organization',
    'tin' => '000-000-000-000',
    'sss' => 'N/A',
    'philhealth' => 'N/A',
    'pagibig' => 'N/A',
    'owners' => ['Organization Owner']
];

// =============================================
// CHART OF ACCOUNTS
// =============================================
$defaultAccounts = [
    '1010' => ['name' => 'Cash', 'type' => 'Asset', 'normal_balance' => 'Debit'],
    '1020' => ['name' => 'Accounts Receivable', 'type' => 'Asset', 'normal_balance' => 'Debit'],
    '1500' => ['name' => 'Equipment', 'type' => 'Asset', 'normal_balance' => 'Debit'],
    '2010' => ['name' => 'Accounts Payable', 'type' => 'Liability', 'normal_balance' => 'Credit'],
    '3010' => ['name' => 'Owner Capital', 'type' => 'Equity', 'normal_balance' => 'Credit'],
    '4010' => ['name' => 'Service Revenue', 'type' => 'Revenue', 'normal_balance' => 'Credit'],
    '4020' => ['name' => 'Sales Revenue', 'type' => 'Revenue', 'normal_balance' => 'Credit'],
    '5010' => ['name' => 'Rent Expense', 'type' => 'Expense', 'normal_balance' => 'Debit'],
    '5020' => ['name' => 'Utilities Expense', 'type' => 'Expense', 'normal_balance' => 'Debit'],
    '5030' => ['name' => 'Supplies Expense', 'type' => 'Expense', 'normal_balance' => 'Debit']
];

// =============================================
// INITIALIZE SESSION DATA
// =============================================
if (!isset($_SESSION['accounts'], $_SESSION['entries'])) {
    $_SESSION['accounts'] = $defaultAccounts;
    $_SESSION['entries'] = [];
}

$accounts = &$_SESSION['accounts'];
$entries = &$_SESSION['entries'];

// =============================================
// HELPER FUNCTIONS
// =============================================
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($amount)
{
    return '₱' . number_format((float)$amount, 2);
}

function validDate($date)
{
    if (!is_string($date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    return checkdate(
        (int)substr($date, 5, 2),
        (int)substr($date, 8, 2),
        (int)substr($date, 0, 4)
    );
}

function accountBalance($code)
{
    global $accounts, $entries;

    if (!isset($accounts[$code])) {
        return 0;
    }

    $debits = 0;
    $credits = 0;

    foreach ($entries as $entry) {
        foreach ($entry['lines'] as $line) {
            if ($line['code'] === $code) {
                $debits += $line['debit'];
                $credits += $line['credit'];
            }
        }
    }

    return $accounts[$code]['normal_balance'] === 'Debit'
        ? $debits - $credits
        : $credits - $debits;
}

function postEntry($date, $description, $lines)
{
    global $accounts, $entries;

    $totalDebit = 0;
    $totalCredit = 0;

    foreach ($lines as $line) {
        if (!isset($accounts[$line['code']])) {
            throw new Exception('Invalid account code.');
        }

        if ($line['debit'] < 0 || $line['credit'] < 0) {
            throw new Exception('Amounts cannot be negative.');
        }

        if ($line['debit'] > 0 && $line['credit'] > 0) {
            throw new Exception(
                'A journal line cannot contain both debit and credit.'
            );
        }

        $totalDebit += $line['debit'];
        $totalCredit += $line['credit'];
    }

    if (
        count($lines) < 2 ||
        abs($totalDebit - $totalCredit) > 0.001 ||
        $totalDebit <= 0
    ) {
        throw new Exception(
            'Journal entry debit and credit totals must be equal.'
        );
    }

    $nextId = 1;

    foreach ($entries as $entry) {
        $nextId = max($nextId, ((int)$entry['id']) + 1);
    }

    $entries[] = [
        'id' => $nextId,
        'date' => $date,
        'description' => $description,
        'lines' => $lines
    ];
}

function reportLines($code)
{
    global $entries;

    $lines = [];

    foreach ($entries as $entry) {
        foreach ($entry['lines'] as $line) {
            if ($line['code'] === $code) {
                $lines[] = [
                    'date' => $entry['date'],
                    'description' => $entry['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit']
                ];
            }
        }
    }

    return $lines;
}

// =============================================
// CSRF PROTECTION
// =============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

// =============================================
// HANDLE FORM SUBMISSIONS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Invalid request. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            // ---------------------------------
            // CASH RECEIPTS AND DISBURSEMENTS
            // ---------------------------------
            if (
                $action === 'receipt' ||
                $action === 'disbursement'
            ) {
                $date = $_POST['date'] ?? '';
                $description = trim($_POST['description'] ?? '');
                $amountInput = $_POST['amount'] ?? '';
                $code = $_POST['account_code'] ?? '';

                if (!validDate($date)) {
                    throw new Exception('Please enter a valid date.');
                }

                if (
                    $description === '' ||
                    strlen($description) > 200
                ) {
                    throw new Exception(
                        'Enter a description of up to 200 characters.'
                    );
                }

                if (
                    !is_string($amountInput) ||
                    !is_numeric($amountInput)
                ) {
                    throw new Exception('Enter a valid amount.');
                }

                $amount = round((float)$amountInput, 2);

                if (!is_finite($amount) || $amount <= 0 || $amount > 999999999999) {
                    throw new Exception(
                        'Enter an amount greater than zero.'
                    );
                }

                if (!isset($accounts[$code]) || $code === '1010') {
                    throw new Exception(
                        'Select a valid non-cash account.'
                    );
                }

                if ($action === 'receipt') {
                    $lines = [
                        [
                            'code' => '1010',
                            'debit' => $amount,
                            'credit' => 0
                        ],
                        [
                            'code' => $code,
                            'debit' => 0,
                            'credit' => $amount
                        ]
                    ];
                } else {
                    $lines = [
                        [
                            'code' => $code,
                            'debit' => $amount,
                            'credit' => 0
                        ],
                        [
                            'code' => '1010',
                            'debit' => 0,
                            'credit' => $amount
                        ]
                    ];
                }

                postEntry($date, $description, $lines);

                $message = $action === 'receipt'
                    ? 'Cash receipt recorded successfully.'
                    : 'Cash disbursement recorded successfully.';

            // ---------------------------------
            // GENERAL JOURNAL
            // ---------------------------------
            } elseif ($action === 'general') {
                $date = $_POST['date'] ?? '';
                $description = trim($_POST['description'] ?? '');
                $debitCode = $_POST['debit_code'] ?? '';
                $creditCode = $_POST['credit_code'] ?? '';
                $amountInput = $_POST['amount'] ?? '';

                if (!validDate($date)) {
                    throw new Exception('Please enter a valid date.');
                }

                if (
                    $description === '' ||
                    strlen($description) > 200
                ) {
                    throw new Exception(
                        'Enter a description of up to 200 characters.'
                    );
                }

                if (
                    !is_string($amountInput) ||
                    !is_numeric($amountInput)
                ) {
                    throw new Exception('Enter a valid amount.');
                }

                $amount = round((float)$amountInput, 2);

                if (
                    !is_finite($amount) ||
                    $amount <= 0 ||
                    $amount > 999999999999
                ) {
                    throw new Exception(
                        'Enter an amount greater than zero.'
                    );
                }

                if (
                    !isset($accounts[$debitCode]) ||
                    !isset($accounts[$creditCode]) ||
                    $debitCode === $creditCode
                ) {
                    throw new Exception(
                        'Select two different valid accounts.'
                    );
                }

                postEntry($date, $description, [
                    [
                        'code' => $debitCode,
                        'debit' => $amount,
                        'credit' => 0
                    ],
                    [
                        'code' => $creditCode,
                        'debit' => 0,
                        'credit' => $amount
                    ]
                ]);

                $message = 'General journal entry recorded successfully.';

            // ---------------------------------
            // RESET SESSION ACCOUNTING DATA
            // ---------------------------------
            } elseif ($action === 'reset') {
                $_SESSION['accounts'] = $defaultAccounts;
                $_SESSION['entries'] = [];

                header('Location: dashboard.php');
                exit();

            } else {
                throw new Exception('Unknown action.');
            }

        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }

    $accounts = &$_SESSION['accounts'];
    $entries = &$_SESSION['entries'];
}

// =============================================
// REPORT TOTALS
// =============================================
$totalReceipts = 0;
$totalDisbursements = 0;

foreach ($entries as $entry) {
    foreach ($entry['lines'] as $line) {
        if ($line['code'] === '1010') {
            $totalReceipts += $line['debit'];
            $totalDisbursements += $line['credit'];
        }
    }
}

$totalRevenue = 0;
$totalExpenses = 0;

foreach ($accounts as $code => $account) {
    $balance = accountBalance($code);

    if ($account['type'] === 'Revenue') {
        $totalRevenue += $balance;
    }

    if ($account['type'] === 'Expense') {
        $totalExpenses += $balance;
    }
}

$netIncome = $totalRevenue - $totalExpenses;
$cashBalance = accountBalance('1010');

// =============================================
// NAVIGATION
// =============================================
$tab = $_GET['tab'] ?? 'dashboard';

$validTabs = [
    'dashboard',
    'profile',
    'receipts',
    'disbursements',
    'journal',
    'ledger',
    'trial',
    'income'
];

if (!in_array($tab, $validTabs, true)) {
    $tab = 'dashboard';
}

$nav = [
    'dashboard' => 'Dashboard',
    'profile' => 'Organization Profile',
    'receipts' => 'Cash Receipts',
    'disbursements' => 'Cash Disbursements',
    'journal' => 'General Journal',
    'ledger' => 'General Ledger',
    'trial' => 'Trial Balance',
    'income' => 'Income Statement'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= h($nav[$tab]) ?> | Simple Accounting System</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f1f5f9;
    color: #1e293b;
    font-family: Arial, sans-serif;
}

header {
    background: #12304a;
    color: white;
    padding: 22px 5%;
}

header h1 {
    margin: 0 0 6px;
    font-size: 25px;
}

.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.user-info {
    margin-top: 12px;
    font-size: 14px;
}

.logout {
    display: inline-block;
    background: #dc2626;
    color: white;
    padding: 9px 14px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}

.logout:hover {
    background: #b91c1c;
}

nav {
    background: #1e293b;
    padding: 12px 5%;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

nav a {
    color: white;
    text-decoration: none;
    padding: 10px 12px;
    border-radius: 5px;
    font-size: 14px;
}

nav a:hover,
nav a.active {
    background: #2563eb;
}

main {
    max-width: 1200px;
    margin: 25px auto;
    padding: 0 18px;
    min-height: 65vh;
}

h2 {
    margin-top: 0;
}

h3 {
    margin-top: 0;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.card,
.panel {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px #0000000a;
    margin-bottom: 20px;
}

.card p {
    color: #64748b;
    margin: 0 0 10px;
}

.card strong {
    font-size: 25px;
    overflow-wrap: anywhere;
}

.card.cash {
    border-top: 4px solid #2563eb;
}

.card.received {
    border-top: 4px solid #16a34a;
}

.card.spent {
    border-top: 4px solid #dc2626;
}

.card.profit {
    border-top: 4px solid #7c3aed;
}

form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

label {
    font-weight: bold;
    font-size: 14px;
    display: block;
}

input,
select {
    display: block;
    width: 100%;
    margin-top: 7px;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: white;
    font: inherit;
}

input:focus,
select:focus {
    outline: 2px solid #93c5fd;
    border-color: #2563eb;
}

button {
    background: #2563eb;
    color: white;
    border: 0;
    padding: 12px 17px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

button:hover {
    background: #1d4ed8;
}

form button {
    align-self: end;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th,
td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
}

th {
    background: #eaf0f6;
}

.notice {
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 18px;
    background: #dcfce7;
    color: #166534;
}

.notice.error {
    background: #fee2e2;
    color: #991b1b;
}

.muted {
    color: #64748b;
}

.section-title {
    margin: 25px 0 12px;
}

.report-table {
    margin-bottom: 24px;
}

.positive {
    color: #15803d;
}

.negative {
    color: #b91c1c;
}

.reset-button {
    background: #dc2626;
}

.reset-button:hover {
    background: #b91c1c;
}

footer {
    text-align: center;
    padding: 25px;
    color: #64748b;
    font-size: 13px;
}

@media (max-width: 600px) {
    header h1 {
        font-size: 21px;
    }

    .card strong {
        font-size: 21px;
    }

    nav {
        padding: 10px;
    }

    nav a {
        font-size: 13px;
        padding: 8px 9px;
    }

    main {
        padding: 0 12px;
    }

    .panel,
    .card {
        padding: 15px;
    }
}
</style>
</head>

<body>

<header>
    <div class="header-row">
        <div>
            <h1>Simple Accounting System</h1>
            <div><?= h($org['name']) ?></div>
        </div>

        <a class="logout"
           href="logout.php"
           onclick="return confirm('Are you sure you want to log out?')">
            Logout
        </a>
    </div>

    <div class="user-info">
        Welcome,
        <strong><?= h($_SESSION['fullname'] ?? 'User') ?></strong>
        <?php if (!empty($_SESSION['useremail'])): ?>
            &middot; <?= h($_SESSION['useremail']) ?>
        <?php endif; ?>
    </div>
</header>

<nav>
<?php foreach ($nav as $key => $label): ?>
    <a class="<?= $tab === $key ? 'active' : '' ?>"
       href="dashboard.php?tab=<?= h($key) ?>">
        <?= h($label) ?>
    </a>
<?php endforeach; ?>
</nav>

<main>

<?php if ($message !== ''): ?>
    <div class="notice <?= $messageType === 'error' ? 'error' : '' ?>"
         role="status">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<!-- =========================================
     DASHBOARD
========================================== -->
<?php if ($tab === 'dashboard'): ?>

<h2>Dashboard</h2>
<p class="muted">Overview of your accounting activity.</p>

<div class="cards">
    <div class="card received">
        <p>Total Cash Received</p>
        <strong><?= money($totalReceipts) ?></strong>
    </div>

    <div class="card spent">
        <p>Total Cash Disbursed</p>
        <strong><?= money($totalDisbursements) ?></strong>
    </div>

    <div class="card cash">
        <p>Cash Balance</p>
        <strong><?= money($cashBalance) ?></strong>
    </div>

    <div class="card profit">
        <p>Net Income</p>
        <strong><?= money($netIncome) ?></strong>
    </div>
</div>

<div class="panel">
    <h3>Recent Journal Entries</h3>

    <?php if (empty($entries)): ?>
        <p class="muted">No transactions recorded yet.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Description</th>
                <th>Total Debit</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice(array_reverse($entries), 0, 10) as $entry): ?>
                <tr>
                    <td><?= h($entry['id']) ?></td>
                    <td><?= h($entry['date']) ?></td>
                    <td><?= h($entry['description']) ?></td>
                    <td>
                        <?= money(array_sum(array_column($entry['lines'], 'debit'))) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>Quick Actions</h3>
    <p class="muted">Choose an accounting task to get started.</p>

    <p>
        <a href="dashboard.php?tab=receipts">Record Cash Receipt</a>
        &nbsp; | &nbsp;
        <a href="dashboard.php?tab=disbursements">Record Cash Disbursement</a>
        &nbsp; | &nbsp;
        <a href="dashboard.php?tab=journal">Create Journal Entry</a>
    </p>
</div>

<!-- =========================================
     ORGANIZATION PROFILE
========================================== -->
<?php elseif ($tab === 'profile'): ?>

<div class="panel">
    <h2>Organization Profile</h2>

    <div class="table-wrap">
    <table>
        <tr>
            <th>Organization Name</th>
            <td><?= h($org['name']) ?></td>
        </tr>
        <tr>
            <th>TIN</th>
            <td><?= h($org['tin']) ?></td>
        </tr>
        <tr>
            <th>SSS Number</th>
            <td><?= h($org['sss']) ?></td>
        </tr>
        <tr>
            <th>PhilHealth Number</th>
            <td><?= h($org['philhealth']) ?></td>
        </tr>
        <tr>
            <th>Pag-IBIG Number</th>
            <td><?= h($org['pagibig']) ?></td>
        </tr>
        <tr>
            <th>Owner(s)</th>
            <td><?= h(implode(', ', $org['owners'])) ?></td>
        </tr>
    </table>
    </div>

    <p class="muted">
        To change these details, edit the organization information
        array at the top of dashboard.php.
    </p>
</div>

<!-- =========================================
     CASH RECEIPTS / DISBURSEMENTS
========================================== -->
<?php elseif ($tab === 'receipts' || $tab === 'disbursements'): ?>

<?php $isReceiptTab = ($tab === 'receipts'); ?>

<div class="panel">
    <h2>
        <?= $isReceiptTab ? 'Record Cash Receipt' : 'Record Cash Disbursement' ?>
    </h2>

    <p class="muted">
        <?php if ($isReceiptTab): ?>
            Cash is debited and the selected account is credited.
        <?php else: ?>
            The selected account is debited and cash is credited.
        <?php endif; ?>
    </p>

    <form method="post" action="dashboard.php?tab=<?= h($tab) ?>">
        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="action"
               value="<?= $isReceiptTab ? 'receipt' : 'disbursement' ?>">

        <label>
            Date
            <input type="date" name="date"
                   value="<?= h(date('Y-m-d')) ?>" required>
        </label>

        <label>
            Description
            <input type="text" name="description"
                   maxlength="200"
                   placeholder="Transaction description" required>
        </label>

        <label>
            Amount (PHP)
            <input type="number" name="amount"
                   min="0.01" max="999999999999"
                   step="0.01" placeholder="0.00" required>
        </label>

        <label>
            Offset Account
            <select name="account_code" required>
                <option value="">Select account</option>
                <?php foreach ($accounts as $code => $account): ?>
                    <?php if ($code !== '1010'): ?>
                        <option value="<?= h($code) ?>">
                            <?= h($code . ' - ' . $account['name']) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit">Save Transaction</button>
    </form>
</div>

<div class="panel">
    <h3>
        <?= $isReceiptTab ? 'Cash Receipts Journal' : 'Cash Disbursement Journal' ?>
    </h3>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Description</th>
            <th>Cash Amount</th>
        </tr>
        </thead>
        <tbody>

        <?php
        $shown = 0;

        foreach ($entries as $entry):
            $cashLine = null;

            foreach ($entry['lines'] as $line) {
                if ($line['code'] === '1010') {
                    $cashLine = $line;
                    break;
                }
            }

            if ($cashLine === null) {
                continue;
            }

            $isReceipt = $cashLine['debit'] > 0;

            if ($isReceipt !== $isReceiptTab) {
                continue;
            }

            $shown++;
        ?>
            <tr>
                <td><?= h($entry['id']) ?></td>
                <td><?= h($entry['date']) ?></td>
                <td><?= h($entry['description']) ?></td>
                <td>
                    <?= money(
                        $isReceipt
                            ? $cashLine['debit']
                            : $cashLine['credit']
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if ($shown === 0): ?>
            <tr>
                <td colspan="4">No transactions recorded yet.</td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>
    </div>
</div>

<!-- =========================================
     GENERAL JOURNAL
========================================== -->
<?php elseif ($tab === 'journal'): ?>

<div class="panel">
    <h2>General Journal</h2>
    <p class="muted">
        Record a balanced debit and credit entry.
    </p>

    <form method="post" action="dashboard.php?tab=journal">
        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="action" value="general">

        <label>
            Date
            <input type="date" name="date"
                   value="<?= h(date('Y-m-d')) ?>" required>
        </label>

        <label>
            Description
            <input type="text" name="description"
                   maxlength="200"
                   placeholder="Journal entry description" required>
        </label>

        <label>
            Debit Account
            <select name="debit_code" required>
                <option value="">Select account</option>
                <?php foreach ($accounts as $code => $account): ?>
                    <option value="<?= h($code) ?>">
                        <?= h($code . ' - ' . $account['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Credit Account
            <select name="credit_code" required>
                <option value="">Select account</option>
                <?php foreach ($accounts as $code => $account): ?>
                    <option value="<?= h($code) ?>">
                        <?= h($code . ' - ' . $account['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Amount (PHP)
            <input type="number" name="amount"
                   min="0.01" max="999999999999"
                   step="0.01" placeholder="0.00" required>
        </label>

        <button type="submit">Post Journal Entry</button>
    </form>
</div>

<div class="panel">
    <h3>Journal Entries</h3>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Description</th>
            <th>Account</th>
            <th>Debit</th>
            <th>Credit</th>
        </tr>
        </thead>
        <tbody>

        <?php if (empty($entries)): ?>
            <tr>
                <td colspan="6">No journal entries recorded yet.</td>
            </tr>
        <?php else: ?>
            <?php foreach (array_reverse($entries) as $entry): ?>
                <?php foreach ($entry['lines'] as $line): ?>
                    <tr>
                        <td><?= h($entry['id']) ?></td>
                        <td><?= h($entry['date']) ?></td>
                        <td><?= h($entry['description']) ?></td>
                        <td>
                            <?= h(
                                $line['code'] . ' - ' .
                                $accounts[$line['code']]['name']
                            ) ?>
                        </td>
                        <td>
                            <?= $line['debit'] > 0
                                ? money($line['debit'])
                                : '—' ?>
                        </td>
                        <td>
                            <?= $line['credit'] > 0
                                ? money($line['credit'])
                                : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
    </table>
    </div>
</div>

<!-- =========================================
     GENERAL LEDGER
========================================== -->
<?php elseif ($tab === 'ledger'): ?>

<h2>General Ledger</h2>

<?php foreach ($accounts as $code => $account): ?>
    <?php $lines = reportLines($code); ?>

    <div class="panel">
        <h3><?= h($code . ' - ' . $account['name']) ?></h3>

        <p class="muted">
            <?= h($account['type']) ?>
            &middot;
            Normal balance: <?= h($account['normal_balance']) ?>
        </p>

        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
            </thead>
            <tbody>

            <?php if (empty($lines)): ?>
                <tr>
                    <td colspan="4">No activity for this account.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?= h($line['date']) ?></td>
                        <td><?= h($line['description']) ?></td>
                        <td>
                            <?= $line['debit'] > 0
                                ? money($line['debit'])
                                : '—' ?>
                        </td>
                        <td>
                            <?= $line['credit'] > 0
                                ? money($line['credit'])
                                : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            <tr>
                <th colspan="2">Ending Balance</th>
                <th colspan="2"><?= money(accountBalance($code)) ?></th>
            </tr>

            </tbody>
        </table>
        </div>
    </div>
<?php endforeach; ?>

<!-- =========================================
     TRIAL BALANCE
========================================== -->
<?php elseif ($tab === 'trial'): ?>

<div class="panel">
    <h2>Trial Balance</h2>
    <p><?= h($org['name']) ?></p>
    <p class="muted">Balances of all accounts with recorded activity.</p>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Code</th>
            <th>Account Title</th>
            <th>Debit</th>
            <th>Credit</th>
        </tr>
        </thead>
        <tbody>

        <?php
        $tbDebit = 0;
        $tbCredit = 0;

        foreach ($accounts as $code => $account):
            $balance = accountBalance($code);
            $debit = 0;
            $credit = 0;

            if ($account['normal_balance'] === 'Debit') {
                if ($balance >= 0) {
                    $debit = $balance;
                } else {
                    $credit = abs($balance);
                }
            } else {
                if ($balance >= 0) {
                    $credit = $balance;
                } else {
                    $debit = abs($balance);
                }
            }

            if (abs($debit) < 0.005 && abs($credit) < 0.005) {
                continue;
            }

            $tbDebit += $debit;
            $tbCredit += $credit;
        ?>
            <tr>
                <td><?= h($code) ?></td>
                <td><?= h($account['name']) ?></td>
                <td><?= money($debit) ?></td>
                <td><?= money($credit) ?></td>
            </tr>
        <?php endforeach; ?>

        <tr>
            <th colspan="2">TOTAL</th>
            <th><?= money($tbDebit) ?></th>
            <th><?= money($tbCredit) ?></th>
        </tr>

        </tbody>
    </table>
    </div>

    <?php $difference = round($tbDebit - $tbCredit, 2); ?>

    <p>
        <strong>Difference:</strong>
        <span class="<?= abs($difference) < 0.005 ? 'positive' : 'negative' ?>">
            <?= money($difference) ?>
        </span>
    </p>

    <?php if (abs($difference) < 0.005): ?>
        <p class="muted">Total debits equal total credits.</p>
    <?php else: ?>
        <p class="muted">
            The trial balance is not balanced. Review the accounts and journal entries.
        </p>
    <?php endif; ?>
</div>

<!-- =========================================
     INCOME STATEMENT
========================================== -->
<?php elseif ($tab === 'income'): ?>

<div class="panel">
    <h2>Income Statement</h2>
    <p><?= h($org['name']) ?></p>
    <p class="muted">
        Revenue and expenses for all transactions currently recorded.
    </p>

    <h3 class="section-title">Revenues</h3>

    <div class="table-wrap">
    <table class="report-table">
        <thead>
        <tr>
            <th>Account</th>
            <th>Amount</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach ($accounts as $code => $account): ?>
            <?php if ($account['type'] === 'Revenue'): ?>
                <tr>
                    <td><?= h($account['name']) ?></td>
                    <td><?= money(accountBalance($code)) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>

        <tr>
            <th>Total Revenue</th>
            <th><?= money($totalRevenue) ?></th>
        </tr>

        </tbody>
    </table>
    </div>

    <h3 class="section-title">Expenses</h3>

    <div class="table-wrap">
    <table class="report-table">
        <thead>
        <tr>
            <th>Account</th>
            <th>Amount</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach ($accounts as $code => $account): ?>
            <?php if ($account['type'] === 'Expense'): ?>
                <tr>
                    <td><?= h($account['name']) ?></td>
                    <td><?= money(accountBalance($code)) ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>

        <tr>
            <th>Total Expenses</th>
            <th><?= money($totalExpenses) ?></th>
        </tr>

        </tbody>
    </table>
    </div>

    <h3>Net Income</h3>
    <p>
        <strong class="<?= $netIncome >= 0 ? 'positive' : 'negative' ?>">
            <?= money($netIncome) ?>
        </strong>
    </p>
</div>

<?php endif; ?>

<!-- =========================================
     SYSTEM MAINTENANCE
========================================== -->
<div class="panel">
    <h3>System Maintenance</h3>

    <p class="muted">
        Resetting clears all accounting transactions stored in the
        current session. This action cannot be undone.
    </p>

    <form method="post"
          action="dashboard.php?tab=<?= h($tab) ?>"
          onsubmit="return confirm('Are you sure? This will clear all accounting transactions in this session.')">

        <input type="hidden" name="csrf_token"
               value="<?= h($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="action" value="reset">

        <button type="submit" class="reset-button">
            Reset All Accounting Data
        </button>
    </form>
</div>

</main>

<footer>
    Simple Computerized Accounting System
    &middot; PHP and MySQL
</footer>

</body>
</html>
