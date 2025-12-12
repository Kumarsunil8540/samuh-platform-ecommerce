document.addEventListener('DOMContentLoaded', function () {
    const groupIdInput = document.getElementById('group_id');
    const rulesContainer = document.getElementById('groupRulesContainer');
    const rulesContent = document.getElementById('groupRulesContent');
    const rulesStatusMessage = document.getElementById('rulesStatusMessage');
    const rulesReadConfirm = document.getElementById('rules_read_confirm');
    const rulesVerifiedHidden = document.getElementById('rules_verified');
    const submitBtn = document.getElementById('submitBtn');
    const stampViewContainer = document.getElementById('stampViewContainer');
    const stampLink = document.getElementById('stampLink');
    const languageToggle = document.getElementById('languageToggle');

    // Language Toggle Logic (Assuming you add this button in HTML)
    let currentLang = 'hi';

    function updateLanguage(lang) {
        currentLang = lang;
        document.querySelectorAll('.lang-hi').forEach(el => el.style.display = lang === 'hi' ? 'block' : 'none');
        document.querySelectorAll('.lang-en').forEach(el => el.style.display = lang === 'en' ? 'block' : 'none');
        // Handle inline block/flex elements
        document.querySelectorAll('.lang-hi').forEach(el => {
            if (lang === 'hi' && (el.tagName === 'SPAN' || el.tagName === 'SMALL' || el.tagName === 'A')) {
                el.style.display = 'inline-block';
            } else if (lang === 'hi' && el.tagName === 'BUTTON') {
                el.style.display = 'inline-block';
            } else if (lang === 'hi') {
                el.style.display = 'block';
            }
        });
        document.querySelectorAll('.lang-en').forEach(el => {
            if (lang === 'en' && (el.tagName === 'SPAN' || el.tagName === 'SMALL' || el.tagName === 'A')) {
                el.style.display = 'inline-block';
            } else if (lang === 'en' && el.tagName === 'BUTTON') {
                el.style.display = 'inline-block';
            } else if (lang === 'en') {
                el.style.display = 'block';
            }
        });
    }

    // Default to Hindi on load
    updateLanguage('hi'); 

    // Enable/Disable Submit Button
    function toggleSubmitButton() {
        const rulesConfirmed = rulesReadConfirm.checked;
        const rulesVerified = rulesVerifiedHidden.value === 'true';
        const agreeTerms = document.getElementById('agree_terms').checked;
        const trustDocuments = document.getElementById('trust_documents').checked;

        submitBtn.disabled = !(rulesConfirmed && rulesVerified && agreeTerms && trustDocuments);
    }

    // Initial check for submit button
    rulesReadConfirm.addEventListener('change', toggleSubmitButton);
    document.getElementById('agree_terms').addEventListener('change', toggleSubmitButton);
    document.getElementById('trust_documents').addEventListener('change', toggleSubmitButton);


    // Group Details Fetch Function (Debounced)
    let fetchTimeout;
    groupIdInput.addEventListener('input', function () {
        clearTimeout(fetchTimeout);
        rulesContainer.style.display = 'none';
        rulesVerifiedHidden.value = 'false';
        rulesReadConfirm.checked = false;
        toggleSubmitButton(); // Disable button immediately

        const groupID = groupIdInput.value.trim();

        if (groupID === '' || !/^\d+$/.test(groupID)) {
            rulesStatusMessage.style.display = 'block';
            rulesStatusMessage.className = 'rules-status-error';
            rulesStatusMessage.innerHTML = currentLang === 'hi' ? '❌ मान्य समूह आईडी दर्ज करें।' : '❌ Enter a valid Group ID.';
            return;
        }

        rulesStatusMessage.style.display = 'block';
        rulesStatusMessage.className = 'rules-status-message';
        rulesStatusMessage.innerHTML = currentLang === 'hi' ? '<span class="loading"></span> समूह विवरण लोड हो रहा है...' : '<span class="loading"></span> Loading group details...';
        
        fetchTimeout = setTimeout(() => {
            fetch(`member_request.php?action=fetch_group_details&group_id=${groupID}`)
                .then(response => response.json())
                .then(data => {
                    rulesContainer.style.display = 'block';

                    if (data.success) {
                        rulesStatusMessage.className = 'rules-status-success';
                        rulesStatusMessage.innerHTML = currentLang === 'hi' ? '✅ विवरण सफलतापूर्वक लोड हुआ।' : '✅ Details loaded successfully.';
                        
                        rulesContent.innerHTML = data.rules;
                        rulesVerifiedHidden.value = 'true';
                        
                        if (data.stamp_path) {
                            stampLink.href = data.stamp_path;
                            stampViewContainer.style.display = 'block';
                        } else {
                            stampViewContainer.style.display = 'none';
                        }
                    } else {
                        rulesStatusMessage.className = 'rules-status-error';
                        rulesStatusMessage.innerHTML = currentLang === 'hi' ? data.message_hi : data.message_en;
                        rulesContent.innerHTML = '';
                        rulesVerifiedHidden.value = 'false';
                        stampViewContainer.style.display = 'none';
                    }
                    toggleSubmitButton(); // Re-check button status
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    rulesContainer.style.display = 'block';
                    rulesStatusMessage.className = 'rules-status-error';
                    rulesStatusMessage.innerHTML = currentLang === 'hi' ? '❌ नेटवर्क त्रुटि।' : '❌ Network Error.';
                    rulesContent.innerHTML = '';
                    rulesVerifiedHidden.value = 'false';
                    stampViewContainer.style.display = 'none';
                    toggleSubmitButton();
                });
        }, 800); // Debounce time
    });

    // Final form submission check (optional, as PHP already does this)
    document.getElementById('membershipForm').addEventListener('submit', function (e) {
        if (rulesVerifiedHidden.value !== 'true') {
            e.preventDefault();
            alert(currentLang === 'hi' ? "❌ कृपया पहले मान्य समूह आईडी दर्ज करें और नियम लोड करें।" : "❌ Please enter a valid Group ID and load rules first.");
        }
    });

    // Check if form was submitted with errors (re-trigger rule fetch on page load if group_id is present)
    if (groupIdInput.value.trim() !== '') {
        groupIdInput.dispatchEvent(new Event('input'));
    }
});