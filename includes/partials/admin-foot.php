<?php
/** Admin console layout — close content + scripts. */
declare(strict_types=1);
if (!defined('KL_BOOTSTRAPPED')) { exit; }
?>
    </div><!-- /.kl-admin-content -->
  </div><!-- /.kl-admin-main -->
</div><!-- /.kl-admin-shell -->
<script src="<?= e_attr(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e_attr(asset('js/app.js')) ?>"></script>
</body>
</html>
