<?php
require_once 'includes/db.php';

$page_title = 'Admission Notices';
include 'includes/header.php';

// Static notices (UI-only). Later you can load these from DB.
$notices = [
    ['title' => 'Registration portal is open', 'date' => '2025-XX-XX', 'body' => 'Eligible students can complete registration using Registration ID (GST Roll) and password.'],
    ['title' => 'Required documents for registration', 'date' => '2025-XX-XX', 'body' => 'Please keep your SSC/HSC certificates, NID/Birth certificate, and recent photos ready.'],
    ['title' => 'Helpdesk & support', 'date' => '2025-XX-XX', 'body' => 'For any issue, contact the Central Admission Office helpdesk during office hours.'],
];
?>

<section class="card" role="region" aria-label="Admission Notices">
  <h2>Admission Notices</h2>

  <div class="alert">
    This page is for University of Barishal admission-related notices.
  </div>

  <div style="display:flex; flex-direction:column; gap:14px;">
    <?php foreach ($notices as $n): ?>
      <div style="border:1px solid #e5e7eb; border-radius:4px; padding:14px 16px;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
          <div style="font-family: Georgia, 'Times New Roman', serif; font-weight:700; color:#111827;">
            <?= htmlspecialchars($n['title']) ?>
          </div>
          <div style="color:#6b7280; font-size:13px; white-space:nowrap;">
            <?= htmlspecialchars($n['date']) ?>
          </div>
        </div>
        <div style="margin-top:8px; color:#374151;">
          <?= htmlspecialchars($n['body']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
