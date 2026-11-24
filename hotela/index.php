<?php
require "config.php";
$page=$_GET["page"]??"dashboard";
if($_SERVER["REQUEST_METHOD"]==="POST"){
 checkcsrf();$a=$_POST["action"]??"";
 if($a==="login"){
  $email=trim($_POST["email"]??"");$pw=$_POST["password"]??"";
  $q=$conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");$q->bind_param("s",$email);$q->execute();$u=$q->get_result()->fetch_assoc();
  if($u && password_verify($pw,$u["password_hash"]) && ($u["role"]==="admin"||$u["status"]==="active")){
   session_regenerate_id(true);$_SESSION["uid"]=(int)$u["id"];$_SESSION["name"]=$u["full_name"];$_SESSION["role"]=$u["role"];msg("Baga nagaan dhuftan.");go();
  } msg("Login hin milkoofne. Email/password ykn approval ilaali.");go();
 }
 if($a==="logout"){$_SESSION=[];session_destroy();go();}
 if($a==="register"){
  $n=trim($_POST["full_name"]??"");$e=trim($_POST["email"]??"");$j=trim($_POST["job_title"]??"");$p=$_POST["password"]??"";
  if(!$n||!filter_var($e,FILTER_VALIDATE_EMAIL)||strlen($p)<8){msg("Odeeffannoo sirrii galchi; password yoo xiqqaate qubee 8.");go("index.php?page=register");}
  $hash=password_hash($p,PASSWORD_DEFAULT);$role="employee";$status="pending";
  $q=$conn->prepare("INSERT INTO users(full_name,email,password_hash,role,job_title,status) VALUES(?,?,?,?,?,?)");$q->bind_param("ssssss",$n,$e,$hash,$role,$j,$status);
  msg($q->execute()?"Galmeen kee ergameera. Admin eeyyama hanga kennutti eegii.":"Email kun duraan jira ykn galmeen hin milkoofne.");go();
 }
 if($a==="add_employee"&&admin()){
  $n=trim($_POST["full_name"]??"");$e=trim($_POST["email"]??"");$j=trim($_POST["job_title"]??"");$s=(float)($_POST["salary"]??0);$p=$_POST["password"]??"";
  if(!$n||!filter_var($e,FILTER_VALIDATE_EMAIL)||$s<0||strlen($p)<8){msg("Odeeffannoo sirrii guuti.");go("index.php?page=employees");}
  $hash=password_hash($p,PASSWORD_DEFAULT);$role="employee";$status="active";
  $q=$conn->prepare("INSERT INTO users(full_name,email,password_hash,role,job_title,salary,status) VALUES(?,?,?,?,?,?,?)");$q->bind_param("sssssds",$n,$e,$hash,$role,$j,$s,$status);
  msg($q->execute()?"Hojjetaan galmaa'eera.":"Email kun duraan jira.");go("index.php?page=employees");
 }
 if(($a==="approve"||$a==="reject")&&admin()){
  $id=(int)$_POST["id"];$st=$a==="approve"?"active":"rejected";$q=$conn->prepare("UPDATE users SET status=? WHERE id=? AND role='employee'");$q->bind_param("si",$st,$id);$q->execute();msg("Galmeen hojjetaa haaromfameera.");go("index.php?page=employees");
 }
 if($a==="report"&&logged()&&!admin()){
  $d=$_POST["report_date"]??date("Y-m-d");$t=trim($_POST["report_text"]??"");$id=(int)$_SESSION["uid"];
  if($t){$q=$conn->prepare("INSERT INTO reports(employee_id,report_date,report_text) VALUES(?,?,?)");$q->bind_param("iss",$id,$d,$t);$q->execute();msg("Gabaasni galmaa'eera.");}else msg("Gabaasa barreessi.");go("index.php?page=myreports");
 }
 if($a==="review"&&admin()){
  $id=(int)$_POST["id"];$st=$_POST["status"]==="approved"?"approved":"rejected";$c=trim($_POST["comment"]??"");
  $q=$conn->prepare("UPDATE reports SET status=?,admin_comment=? WHERE id=?");$q->bind_param("ssi",$st,$c,$id);$q->execute();msg("Gabaasni ilaalameera.");go("index.php?page=reports");
 }
 if($a==="pay"&&admin()){
  $id=(int)$_POST["employee_id"];$amount=(float)$_POST["amount"];$date=$_POST["payment_date"]??date("Y-m-d");$note=trim($_POST["note"]??"");
  $q=$conn->prepare("INSERT INTO salary_payments(employee_id,amount,payment_date,note) SELECT id,?,?,? FROM users WHERE id=? AND role='employee'");$q->bind_param("dssi",$amount,$date,$note,$id);$q->execute();msg("Kaffaltiin galmaa'eera.");go("index.php?page=salary");
 }
}
if(isset($_SESSION["msg"])){$notice=$_SESSION["msg"];unset($_SESSION["msg"]);}else $notice="";
if(!logged()&&!in_array($page,["register"],true))$page="login";
if(logged()&&$page==="login")$page="dashboard";
if(!admin()&&in_array($page,["employees","reports","salary"],true))$page="denied";
?>
<!doctype html><html lang="om"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TEKU GRAND HOTEL</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f4f7;color:#172033;font-family:Arial,sans-serif}header{background:#101b2d;color:white;padding:18px 5%;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}h1{color:#d4af37;margin:0;font-size:25px}.nav{display:flex;gap:14px;align-items:center;flex-wrap:wrap}.nav a{color:white;text-decoration:none}main{max-width:1100px;margin:25px auto;padding:0 15px}.card{background:white;padding:22px;border-radius:10px;margin-bottom:18px;box-shadow:0 3px 12px #0001}.login{max-width:430px;margin:50px auto}label{display:block;font-weight:bold;margin:12px 0 5px}input,textarea,select{width:100%;padding:11px;border:1px solid #ccd2dc;border-radius:6px;font:inherit}textarea{min-height:100px}button,.btn{background:#101b2d;color:white;padding:10px 14px;border:0;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-block;margin-top:10px}.gold{background:#d4af37;color:#111}.notice{background:#e3f5e8;padding:12px;border-radius:6px;margin-bottom:15px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.stat{font-size:28px;font-weight:bold}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:9px;border-bottom:1px solid #ddd;vertical-align:top}th{background:#f5f6f8}.inline{display:inline-block;margin-right:5px}.small{font-size:13px;color:#697386}
</style></head><body>
<header><div><h1>TEKU GRAND HOTEL</h1><div>Hotel Management System</div></div>
<?php if(logged()):?><nav class="nav"><a href="index.php?page=dashboard">Dashboard</a><?php if(admin()):?><a href="index.php?page=employees">Employees</a><a href="index.php?page=reports">Reports</a><a href="index.php?page=salary">Salary</a><?php else:?><a href="index.php?page=myreports">My Reports</a><?php endif;?>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button>Logout</button></form></nav><?php endif;?></header>
<main><?php if($notice):?><div class="notice"><?=h($notice)?></div><?php endif;?>
<?php if($page==="login"):?><section class="card login"><h2>Login</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login"><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" required><button>Login</button></form><p class="small">Admin jalqabaa: admin@teku.com / Admin123!</p><a href="index.php?page=register">Employee registration</a></section>
<?php elseif($page==="register"):?><section class="card login"><h2>Employee Registration Request</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="register"><label>Full name</label><input name="full_name" required><label>Email</label><input type="email" name="email" required><label>Job title</label><input name="job_title"><label>Password (8+ characters)</label><input type="password" name="password" minlength="8" required><button>Send Registration</button></form><a href="index.php">Back to login</a></section>
<?php elseif($page==="dashboard"):?><section class="card"><h2>Baga nagaan dhuftan, <?=h($_SESSION["name"])?></h2><p>Role: <?=h($_SESSION["role"])?></p><?php if(admin()):?><div class="grid"><?php foreach(["Employees"=>"SELECT COUNT(*) n FROM users WHERE role='employee'","Reports"=>"SELECT COUNT(*) n FROM reports","Salary payments"=>"SELECT COUNT(*) n FROM salary_payments"] as $label=>$sql):?><div class="card"><div><?=h($label)?></div><div class="stat"><?=(int)$conn->query($sql)->fetch_assoc()["n"]?></div></div><?php endforeach;?></div><a class="btn" href="index.php?page=employees">Manage Employees</a> <a class="btn" href="index.php?page=reports">Reports</a> <a class="btn" href="index.php?page=salary">Salary</a><?php else:?><p>Gabaasa hojii kee galchi.</p><a class="btn" href="index.php?page=myreports">My Reports</a><?php endif;?></section>
<?php elseif($page==="employees"&&admin()):?><section class="card"><h2>Register New Employee</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="add_employee"><label>Full name</label><input name="full_name" required><label>Email</label><input type="email" name="email" required><label>Job title</label><input name="job_title"><label>Monthly salary (ETB)</label><input type="number" name="salary" min="0" step=".01" required><label>Temporary password (8+)</label><input type="password" name="password" minlength="8" required><button>Register Employee</button></form></section>
<section class="card"><h2>Employee List and Requests</h2><table><tr><th>Name</th><th>Email</th><th>Job</th><th>Salary</th><th>Status</th><th>Action</th></tr><?php $rs=$conn->query("SELECT * FROM users WHERE role='employee' ORDER BY id DESC");while($r=$rs->fetch_assoc()):?><tr><td><?=h($r["full_name"])?></td><td><?=h($r["email"])?></td><td><?=h($r["job_title"])?></td><td><?=number_format((float)$r["salary"],2)?></td><td><?=h($r["status"])?></td><td><?php if($r["status"]==="pending"):?><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r["id"]?>"><button name="action" value="approve">Approve</button></form><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r["id"]?>"><button name="action" value="reject">Reject</button></form><?php endif;?></td></tr><?php endwhile;?></table></section>
<?php elseif($page==="myreports"&&logged()&&!admin()):?><section class="card"><h2>Submit Daily Report</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="report"><label>Date</label><input type="date" name="report_date" value="<?=date("Y-m-d")?>" required><label>Report</label><textarea name="report_text" required></textarea><button>Submit Report</button></form></section><section class="card"><h2>My Reports</h2><table><tr><th>Date</th><th>Report</th><th>Status</th><th>Comment</th></tr><?php $id=(int)$_SESSION["uid"];$q=$conn->prepare("SELECT report_date,report_text,status,admin_comment FROM reports WHERE employee_id=? ORDER BY id DESC");$q->bind_param("i",$id);$q->execute();$rs=$q->get_result();while($r=$rs->fetch_assoc()):?><tr><td><?=h($r["report_date"])?></td><td><?=nl2br(h($r["report_text"]))?></td><td><?=h($r["status"])?></td><td><?=h($r["admin_comment"])?></td></tr><?php endwhile;?></table></section>
<?php elseif($page==="reports"&&admin()):?><section class="card"><h2>All Employee Reports</h2><table><tr><th>Employee</th><th>Date</th><th>Report</th><th>Review</th></tr><?php $rs=$conn->query("SELECT r.*,u.full_name FROM reports r JOIN users u ON u.id=r.employee_id ORDER BY r.id DESC");while($r=$rs->fetch_assoc()):?><tr><td><?=h($r["full_name"])?></td><td><?=h($r["report_date"])?></td><td><?=nl2br(h($r["report_text"]))?></td><td><?=h($r["status"])?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?=(int)$r["id"]?>"><input name="comment" placeholder="Admin comment"><button name="status" value="approved">Approve</button><button name="status" value="rejected">Reject</button></form></td></tr><?php endwhile;?></table></section>
<?php elseif($page==="salary"&&admin()):?><section class="card"><h2>Record Salary Payment</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="pay"><label>Employee</label><select name="employee_id" required><option value="">Select employee</option><?php $rs=$conn->query("SELECT id,full_name FROM users WHERE role='employee' AND status='active'");while($e=$rs->fetch_assoc()):?><option value="<?=(int)$e["id"]?>"><?=h($e["full_name"])?></option><?php endwhile;?></select><label>Amount paid (ETB)</label><input type="number" name="amount" min=".01" step=".01" required><label>Payment date</label><input type="date" name="payment_date" value="<?=date("Y-m-d")?>" required><label>Note</label><input name="note"><button>Save Payment</button></form></section><section class="card"><h2>Payment History</h2><table><tr><th>Employee</th><th>Amount</th><th>Date</th><th>Note</th></tr><?php $rs=$conn->query("SELECT u.full_name,s.* FROM salary_payments s JOIN users u ON u.id=s.employee_id ORDER BY s.id DESC");while($r=$rs->fetch_assoc()):?><tr><td><?=h($r["full_name"])?></td><td><?=number_format((float)$r["amount"],2)?></td><td><?=h($r["payment_date"])?></td><td><?=h($r["note"])?></td></tr><?php endwhile;?></table></section>
<?php else:?><section class="card"><h2>Access denied / Page not found</h2><a href="index.php?page=dashboard">Back</a></section><?php endif;?></main></body></html>
