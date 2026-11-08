            </div> <!-- end content-container -->
        </div> <!-- end main-content -->
    </div> <!-- end main-wrapper -->

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
    
    <!-- Mobile Sidebar Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebar = document.querySelector('.sidebar-toggle');
            if (toggleSidebar) {
                toggleSidebar.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html> 