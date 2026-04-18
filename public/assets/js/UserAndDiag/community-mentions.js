function setupMentions(inputElementOrId) {
    const inputEl = typeof inputElementOrId === 'string' ? document.getElementById(inputElementOrId) : inputElementOrId;
    if (!inputEl) return;

    let mentionDropdown = document.getElementById('mentionDropdown');
    if (!mentionDropdown) {
        mentionDropdown = document.createElement('div');
        mentionDropdown.id = 'mentionDropdown';
        mentionDropdown.style.cssText = 'position:absolute; background:#1e1e1e; border:1px solid rgba(255,255,255,0.1); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.5); z-index:10000; max-height:200px; overflow-y:auto; display:none; width:220px; font-family: inherit;';
        document.body.appendChild(mentionDropdown);
    }

    inputEl.addEventListener('input', function (e) {
        const val = this.value;
        const cursorPos = this.selectionStart;

        // Find the last @ before cursor
        const lastAtPos = val.lastIndexOf('@', cursorPos - 1);

        if (lastAtPos !== -1 && (lastAtPos === 0 || val.charAt(lastAtPos - 1) === ' ' || val.charAt(lastAtPos - 1) === '\n')) {
            const query = val.substring(lastAtPos + 1, cursorPos);

            // Only search if there's no space in the query (which breaks a single @ tag)
            if (!query.includes(' ') && !query.includes('\n')) {
                fetch(`/user-and-diag/community/api/users/search?q=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(users => {
                        if (users.length > 0) {
                            mentionDropdown.innerHTML = '';
                            users.forEach(u => {
                                const item = document.createElement('div');
                                item.style.cssText = 'padding:8px 12px; cursor:pointer; display:flex; align-items:center; color:white; border-bottom:1px solid rgba(255,255,255,0.05); font-size: 0.9rem; transition: 0.2s;';
                                item.onmouseover = () => item.style.background = 'rgba(255,255,255,0.1)';
                                item.onmouseout = () => item.style.background = 'transparent';

                                const avatarHtml = u.avatar
                                    ? `<img src="${u.avatar}" style="width:24px;height:24px;object-fit:cover;border-radius:50%;margin-right:10px;">`
                                    : `<div style="width:24px;height:24px;border-radius:50%;margin-right:10px;background:#E0F2F1;color:#00695C;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold;">${u.prenom.charAt(0).toUpperCase()}</div>`;

                                item.innerHTML = avatarHtml + `<span style="font-weight:600;margin-right:6px;">${u.prenom}</span><span style="opacity:0.6;font-size:0.85em;">${u.nom}</span>`;

                                item.onclick = function () {
                                    inputEl.value = val.substring(0, lastAtPos) + '@' + u.prenom + ' ' + val.substring(cursorPos);
                                    mentionDropdown.style.display = 'none';
                                    inputEl.focus();
                                    inputEl.dispatchEvent(new Event('input', { bubbles: true })); // trigger auto-resize
                                };
                                mentionDropdown.appendChild(item);
                            });

                            // Position logic
                            mentionDropdown.style.display = 'block';
                            const rect = inputEl.getBoundingClientRect();
                            const dropdownHeight = mentionDropdown.offsetHeight;
                            const spaceBelow = window.innerHeight - rect.bottom;

                            if (spaceBelow < dropdownHeight + 10 && rect.top > dropdownHeight) {
                                // Render above the input if there's no space below
                                mentionDropdown.style.top = (rect.top + window.scrollY - dropdownHeight - 5) + 'px';
                            } else {
                                // Render below
                                mentionDropdown.style.top = (rect.bottom + window.scrollY) + 'px';
                            }
                            mentionDropdown.style.left = (rect.left + window.scrollX) + 'px';
                        } else {
                            mentionDropdown.style.display = 'none';
                        }
                    });
                return;
            }
        }
        mentionDropdown.style.display = 'none';
    });

    // Hide if clicked outside
    document.addEventListener('click', function (e) {
        if (e.target !== inputEl && !mentionDropdown.contains(e.target)) {
            mentionDropdown.style.display = 'none';
        }
    });

    // Hide on scroll of the window or container to avoid floating misalignments
    window.addEventListener('scroll', () => mentionDropdown.style.display = 'none', { passive: true });
}
