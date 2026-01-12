            </main>
            
            <!-- Footer -->
            <footer class="footer">
                <div>
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
                </div>
                <div>
                    <span style="margin-right: 20px;">Versão: <?php echo SYSTEM_VERSION; ?></span>
                    <span>Sessão: <?php echo getSessionTime(); ?></span>
                </div>
            </footer>
        </div>
    </div>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
