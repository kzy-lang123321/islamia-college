        </main>
        <!-- Persistent Institutional Footer -->
        <footer style="padding: 18px 28px; border-top: 1px solid var(--border-card); background: var(--bg-surface); display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted); flex-wrap: wrap; gap: 10px;">
            <div>Islamia College of Technology • Matta Swat, Khyber Pakhtunkhwa</div>
            <div style="color: var(--text-dim);">College Management System • Secured with RBAC & CSRF</div>
        </footer>
    </div>
</div>

<!-- GLOBAL THEME PALETTE PICKER MODAL -->
<div class="modal-overlay" id="themePickerModal">
    <div class="modal-dialog" style="max-width:760px;">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">🎨</span>
                <div>
                    <h3 class="modal-title" style="margin:0;">Colors &amp; Theme Changing Options</h3>
                    <p style="font-size:12.5px;color:var(--text-muted);margin:2px 0 0;">Click any card to instantly apply that palette across the entire application</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('themePickerModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="theme-picker-grid" style="margin-top:0;">
                <!-- ⭐ Suggested Light -->
                <div class="theme-card-option" data-theme-key="light" onclick="setTheme('light')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">⭐ Suggested Light</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                        <span class="theme-card-badge">Default</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F4F7FB;color:#172033;">#F4F7FB</div>
                        <div class="theme-preview-swatch" style="background:#155EEF;color:#ffffff;">#155EEF</div>
                        <div class="theme-preview-swatch" style="background:#0EA5E9;color:#ffffff;">#0EA5E9</div>
                        <div class="theme-preview-swatch" style="background:#7C3AED;color:#ffffff;">#7C3AED</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Clean light background, royal blue brand, cyan accents.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 1. Clean Blue -->
                <div class="theme-card-option" data-theme-key="clean-blue" onclick="setTheme('clean-blue')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">1. Clean Blue</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F5F9FF;color:#172033;">#F5F9FF</div>
                        <div class="theme-preview-swatch" style="background:#1565C0;color:#ffffff;">#1565C0</div>
                        <div class="theme-preview-swatch" style="background:#00A6E8;color:#ffffff;">#00A6E8</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D0E1F9;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Fresh modern blue palette with crisp high contrast.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 2. Royal Blue -->
                <div class="theme-card-option" data-theme-key="royal-blue" onclick="setTheme('royal-blue')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">2. Royal Blue</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F4F7FC;color:#172033;">#F4F7FC</div>
                        <div class="theme-preview-swatch" style="background:#243B7A;color:#ffffff;">#243B7A</div>
                        <div class="theme-preview-swatch" style="background:#3B82F6;color:#ffffff;">#3B82F6</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D5DFEE;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Authoritative collegiate deep royal blue &amp; bright blue.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 3. Teal Academic -->
                <div class="theme-card-option" data-theme-key="teal" onclick="setTheme('teal')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">3. Teal Academic</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F2FAF9;color:#17313B;">#F2FAF9</div>
                        <div class="theme-preview-swatch" style="background:#087F8C;color:#ffffff;">#087F8C</div>
                        <div class="theme-preview-swatch" style="background:#14B8A6;color:#ffffff;">#14B8A6</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#17313B;border-left:1px solid #CFEAE6;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Calm, balanced teal tones for extended screen time.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 4. Purple Academic -->
                <div class="theme-card-option" data-theme-key="purple" onclick="setTheme('purple')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">4. Purple Academic</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F8F6FF;color:#24134F;">#F8F6FF</div>
                        <div class="theme-preview-swatch" style="background:#5B2CC9;color:#ffffff;">#5B2CC9</div>
                        <div class="theme-preview-swatch" style="background:#7C5CFC;color:#ffffff;">#7C5CFC</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#24134F;border-left:1px solid #DFD5FA;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Distinguished university purple with bright accents.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 5. Navy + Cyan -->
                <div class="theme-card-option" data-theme-key="navy-cyan" onclick="setTheme('navy-cyan')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">5. Navy + Cyan</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F3F7FB;color:#172033;">#F3F7FB</div>
                        <div class="theme-preview-swatch" style="background:#123B63;color:#ffffff;">#123B63</div>
                        <div class="theme-preview-swatch" style="background:#00AEEF;color:#ffffff;">#00AEEF</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D3DFEC;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Deep corporate navy paired with vibrant cyan highlights.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 6. Slate + Blue -->
                <div class="theme-card-option" data-theme-key="slate-blue" onclick="setTheme('slate-blue')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">6. Slate + Blue</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F6F8FA;color:#0F172A;">#F6F8FA</div>
                        <div class="theme-preview-swatch" style="background:#334155;color:#ffffff;">#334155</div>
                        <div class="theme-preview-swatch" style="background:#2563EB;color:#ffffff;">#2563EB</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#0F172A;border-left:1px solid #D5DDE6;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Minimalist slate gray structure with bright blue actions.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 7. Green Academic -->
                <div class="theme-card-option" data-theme-key="green" onclick="setTheme('green')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">7. Green Academic</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F4FAF7;color:#17342A;">#F4FAF7</div>
                        <div class="theme-preview-swatch" style="background:#176B4D;color:#ffffff;">#176B4D</div>
                        <div class="theme-preview-swatch" style="background:#22A06B;color:#ffffff;">#22A06B</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#17342A;border-left:1px solid #D1E7DC;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Natural emerald and forest green scholastic theme.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 8. Warm Professional -->
                <div class="theme-card-option" data-theme-key="warm" onclick="setTheme('warm')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">8. Warm Professional</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#FAF9F6;color:#1F2937;">#FAF9F6</div>
                        <div class="theme-preview-swatch" style="background:#374151;color:#ffffff;">#374151</div>
                        <div class="theme-preview-swatch" style="background:#D97706;color:#ffffff;">#D97706</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#1F2937;border-left:1px solid #DDD7CE;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Warm cream background with warm charcoal and amber.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- Classic Dark Navy -->
                <div class="theme-card-option" data-theme-key="dark" onclick="setTheme('dark')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">🌙 Classic Dark Navy</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                        <span class="theme-card-badge">Dark</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#0A0F1D;color:#f8fafc;">#0A0F1D</div>
                        <div class="theme-preview-swatch" style="background:#0284C7;color:#ffffff;">#0284C7</div>
                        <div class="theme-preview-swatch" style="background:#38BDF8;color:#0A0F1D;">#38BDF8</div>
                        <div class="theme-preview-swatch" style="background:#131D35;color:#f8fafc;border-left:1px solid rgba(255,255,255,0.1);">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Deep space navy futuristic high-contrast dark theme.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-glass" onclick="closeModal('themePickerModal')">Close</button>
        </div>
    </div>
</div>

<!-- Global Toast Container -->
<div id="toast-container" class="toast-container"></div>

<!-- Core Application Script -->
<script src="<?= $baseUri ?>/assets/js/app.js"></script>

<?php
$flash = getFlashMessage();
if ($flash):
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif; ?>
</body>
</html>
