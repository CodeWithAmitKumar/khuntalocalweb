<?php
/** Layout: close main, site footer, mobile bottom nav, scripts. */
declare(strict_types=1);
if (!defined('KL_BOOTSTRAPPED')) { exit; }
$year = date('Y');
?>
</main><!-- /.container -->

<footer class="kl-footer">
  <div class="container container-kl">
    <div class="row g-4">
      <div class="col-12 col-md-4">
        <div class="kl-brand text-white mb-2">
          <span class="kl-logo-mark">K</span><span><?= e(site_name()) ?></span>
        </div>
        <p class="small mb-2" style="max-width:320px">
          <?= e((string) setting('site_tagline', 'Local news from Khunta & Mayurbhanj')) ?>.
          Community reporting, reviewed before publication.
        </p>
      </div>
      <div class="col-6 col-md-2">
        <h6>Explore</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="<?= e_attr(base_url()) ?>">Home</a></li>
          <li><a href="<?= e_attr(base_url('latest.php')) ?>">Latest</a></li>
          <li><a href="<?= e_attr(base_url('categories.php')) ?>">Categories</a></li>
          <li><a href="<?= e_attr(base_url('latest.php?sort=trending')) ?>">Trending</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-2">
        <h6>Participate</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="<?= e_attr(base_url('submit-news.php')) ?>">Submit news</a></li>
          <li><a href="<?= e_attr(base_url('register.php')) ?>">Become a reporter</a></li>
          <li><a href="<?= e_attr(base_url('login.php')) ?>">Log in</a></li>
        </ul>
      </div>
      <div class="col-12 col-md-4">
        <h6>About KhuntaLocal</h6>
        <p class="small mb-0">
          We prioritise accuracy, transparency and moderation. Our review process
          collects evidence and flags concerns for human verification — we never
          claim an automated system can prove a story is absolutely true.
        </p>
      </div>
    </div>
    <hr class="my-4" style="border-color:rgba(255,255,255,.12)">
    <div class="d-flex flex-wrap justify-content-between gap-2 small">
      <span>© <?= e($year) ?> <?= e(site_name()) ?>. All rights reserved.</span>
      <span class="text-white-50">Khunta · Mayurbhanj · Odisha</span>
    </div>
  </div>
</footer>

<?php require __DIR__ . '/bottom-nav.php'; ?>

<script src="<?= e_attr(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e_attr(asset('js/app.js')) ?>"></script>
</body>
</html>
