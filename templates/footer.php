<?php
// File: templates/footer.php
// Closes the page that header.php opened.
?>
<?php if (!empty($isGuest)): ?>
      <p class="auth-disclaimer">MajorCare is a student project. It does not replace professional medical services.</p>
  </main>
<?php else: ?>
      </main>
      <footer class="app-footer">MajorCare is a student project. It does not replace professional medical services.</footer>
    </div>
  </div>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/password.js')) ?>" defer></script>
</body>
</html>
