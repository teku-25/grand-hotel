<body>
<header>
  <div><h1>TEKU GRAND HOTEL</h1><div class="small" style="color:#dce3ef">Hotel Management System</div></div>
  <?php if (is_logged_in()): ?>
  <nav class="nav">
    <a href="index.php?page=dashboard">Dashboard</a>
    <?php if (is_admin()): ?><a href="index.php?page=employees">Employees</a><a href="index.php?page=reports">Reports</a><a href="index.php?page=salary">Salary</a><?php else: ?><a href="index.php?page=myreports">My Reports</a><?php endif; ?>
    <form method="post" style="display:inline"><input type="hidden" name="action" value="logout"><button type="submit">Logout</button></form>
  </nav>
  <?php endif; ?>
</header>
<main>
<?php if ($message): ?><div class="notice"><?=h($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?=h($error)?></div><?php endif; ?>

<?php if (!is_logged_in()): ?>
<section class="card login">
<h2>Login</h2>
<form method="post">
<input type="hidden" name="action" value="login">
<label>Email</label><input type="email" name="email" required>
<label>Password</label><input type="password" name="password" required>
<button type="submit">Login</button>
</form>
<p class="small">Admin jalqabaa: admin@teku.com / Admin123!</p>
</section>

<?php elseif ($page === 'dashboard'): ?>
<section class="card">
<h2>Welcome, <?=h($_SESSION['full_name'])?></h2>
<p>Role: <strong><?=h(ucfirst($_SESSION['role']))?></strong></p>
<?php if (is_admin()): ?>
<div class="grid">
<div class="card"><div class="small">Employees</div><div class="stat"><?=(int)$conn->query("SELECT COUNT(*) n FROM users WHERE role='employee'")->fetch_assoc()['n']?></div></div>
<div class="card"><div class="small">Reports</div><div class="stat"><?=(int)$conn->query("SELECT COUNT(*) n FROM reports")->fetch_assoc()['n']?></div></div>
<div class="card"><div class="small">Salary payments</div><div class="stat"><?=(int)$conn->query("SELECT COUNT(*) n FROM salary_payments")->fetch_assoc()['n']?></div></div>
</div>
<p><a class="btn" href="index.php?page=employees">Manage Employees</a> <a class="btn" href="index.php?page=reports">View Reports</a> <a class="btn" href="index.php?page=salary">Salary</a></p>
<?php else: ?>
<p>Guyyaa hojii kee gabaasa galmeessuuf armaan gadii fayyadami.</p>
<a class="btn" href="index.php?page=myreports">Add Daily Report</a>
<?php endif; ?>
</section>

<?php elseif ($page === 'employees' && is_admin()): ?>
<section class="card">
<h2>Register New Employee</h2>
<form method="post">
<input type="hidden" name="action" value="add_employee">
<label>Full name</label><input name="full_name" required>
<label>Email</label><input type="email" name="email" required>
<label>Job title</label><input name="job_title" placeholder="e.g. Receptionist">
<label>Monthly salary</label><input type="number" name="salary" min="0" step="0.01" required>
<label>Temporary password (at least 8 characters)</label><input type="password" name="password" minlength="8" required>
<button type="submit">Register Employee</button>
</form>
</section>
<section class="card">
<h2>Employee List</h2>
<table><tr><th>Name</th><th>Email</th><th>Job</th><th>Monthly salary</th></tr>
<?php $res=$conn->query("SELECT full_name,email,job_title,salary FROM users WHERE role='employee' ORDER BY id DESC"); while($r=$res->fetch_assoc()): ?>
<tr><td><?=h($r['full_name'])?></td><td><?=h($r['email'])?></td><td><?=h($r['job_title'])?></td><td><?=number_format((float)$r['salary'],2)?></td></tr>
<?php endwhile; ?></table>
</section>

<?php elseif ($page === 'myreports' && !is_admin()): ?>
<section class="card">
<h2>Submit Daily Report</h2>
<form method="post">
<input type="hidden" name="action" value="add_report">
<label>Date</label><input type="date" name="report_date" value="<?=date('Y-m-d')?>" required>
<label>Report</label><textarea name="report_text" placeholder="Write your daily work report..." required></textarea>
<button type="submit">Submit Report</button>
</form>
</section>
<section class="card">
<h2>My Reports</h2>
<table><tr><th>Date</th><th>Report</th></tr>
<?php $uid=(int)$_SESSION['user_id']; $stmt=$conn->prepare("SELECT report_date,report_text FROM reports WHERE employee_id=? ORDER BY id DESC"); $stmt->bind_param("i",$uid); $stmt->execute(); $res=$stmt->get_result(); while($r=$res->fetch_assoc()): ?>
<tr><td><?=h($r['report_date'])?></td><td><?=nl2br(h($r['report_text']))?></td></tr>
<?php endwhile; $stmt->close(); ?>
</table>
</section>

<?php elseif ($page === 'reports' && is_admin()): ?>
<section class="card">
<h2>All Employee Reports</h2>
<table><tr><th>Employee</th><th>Date</th><th>Report</th></tr>
<?php $res=$conn->query("SELECT u.full_name,r.report_date,r.report_text FROM reports r JOIN users u ON r.employee_id=u.id ORDER BY r.id DESC"); while($r=$res->fetch_assoc()): ?>
<tr><td><?=h($r['full_name'])?></td><td><?=h($r['report_date'])?></td><td><?=nl2br(h($r['report_text']))?></td></tr>
<?php endwhile; ?></table>
</section>

<?php elseif ($page === 'salary' && is_admin()): ?>
<section class="card">
<h2>Record Salary Payment</h2>
<form method="post">
<input type="hidden" name="action" value="pay_salary">
<label>Employee</label><select name="employee_id" required><option value="">Select employee</option>
<?php $res=$conn->query("SELECT id,full_name FROM users WHERE role='employee' ORDER BY full_name"); while($e=$res->fetch_assoc()): ?>
<option value="<?=(int)$e['id']?>"><?=h($e['full_name'])?></option>
<?php endwhile; ?></select>
<label>Amount paid</label><input type="number" name="amount" min="0.01" step="0.01" required>
<label>Payment date</label><input type="date" name="payment_date" value="<?=date('Y-m-d')?>" required>
<label>Note</label><input name="note" placeholder="Optional note">
<button type="submit">Save Payment</button>
</form>
</section>
<section class="card">
<h2>Payment History</h2>
<table><tr><th>Employee</th><th>Amount</th><th>Date</th><th>Note</th></tr>
<?php $res=$conn->query("SELECT u.full_name,s.amount,s.payment_date,s.note FROM salary_payments s JOIN users u ON s.employee_id=u.id ORDER BY s.id DESC"); while($r=$res->fetch_assoc()): ?>
<tr><td><?=h($r['full_name'])?></td><td><?=number_format((float)$r['amount'],2)?></td><td><?=h($r['payment_date'])?></td><td><?=h($r['note'])?></td></tr>
<?php endwhile; ?></table>
</section>

<?php else: ?>
<section class="card"><h2>Page not found</h2><a href="index.php?page=dashboard">Back to dashboard</a></section>
<?php endif; ?>
</main>
</body>
</html>