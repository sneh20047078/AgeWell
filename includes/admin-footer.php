            </div><!-- end content-container -->
        </div><!-- end main-content -->
    </div><!-- end main-wrapper -->
    
    <footer class="dashboard-footer">
        <div class="footer-content">
            <div class="footer-copyright">
                <p>&copy; <?php echo date('Y'); ?> AgeWell Senior Care. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Admin Scripts -->
    <script src="assets/js/admin.js"></script>
    
    <?php if (isset($extraJS)): ?>
        <script src="<?php echo $extraJS; ?>"></script>
    <?php endif; ?>
    
    <?php if (isset($pageSpecificJS)): ?>
        <script>
            <?php echo $pageSpecificJS; ?>
        </script>
    <?php endif; ?>
</body>
</html> 