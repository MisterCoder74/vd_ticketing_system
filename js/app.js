document.addEventListener('DOMContentLoaded', () => {
    // State management
    const state = {
        currentTicket: null,
        technicians: [],
        charts: {
            status: null,
            priority: null
        }
    };

    // UI Elements
    const sections = {
        dashboard: document.getElementById('ticket-dashboard'),
        create: document.getElementById('ticket-create'),
        details: document.getElementById('ticket-details'),
        logs: document.getElementById('activity-logs'),
        users: document.getElementById('admin-users'),
        stats: document.getElementById('admin-stats')
    };

    /**
     * Routing Logic
     */
    async function router() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action') || 'dashboard';
        const id = urlParams.get('id');

        switch (action) {
            case 'dashboard':
                await showDashboard();
                break;
            case 'view_ticket':
                if (id) {
                    await showTicketDetails(id);
                } else {
                    navigateTo('index.php?action=dashboard');
                }
                break;
            case 'create_ticket':
                showSection('create');
                break;
            case 'logs':
                if (['admin', 'technician'].includes(window.APP_CONFIG.userRole)) {
                    await showLogs();
                } else {
                    navigateTo('index.php?action=dashboard');
                }
                break;
            case 'users':
                if (window.APP_CONFIG.userRole === 'admin') {
                    await showUsers();
                } else {
                    navigateTo('index.php?action=dashboard');
                }
                break;
            case 'stats':
                if (window.APP_CONFIG.userRole === 'admin') {
                    await showStats();
                } else {
                    navigateTo('index.php?action=dashboard');
                }
                break;
            default:
                await showDashboard();
        }
    }

    function navigateTo(url) {
        window.history.pushState({}, '', url);
        router();
    }

    window.addEventListener('popstate', router);

    /**
     * Event Delegation
     */
    const appViewport = document.getElementById('app-viewport');
    if (appViewport) {
        appViewport.addEventListener('click', async (e) => {
            // View Ticket
            const viewBtn = e.target.closest('.btn-view-ticket');
            if (viewBtn) {
                e.preventDefault();
                const id = viewBtn.dataset.id;
                navigateTo(`index.php?action=view_ticket&id=${id}`);
                return;
            }

            // Delete Ticket
            const deleteBtn = e.target.closest('.btn-delete-ticket');
            if (deleteBtn) {
                e.preventDefault();
                const id = deleteBtn.dataset.id;
                if (confirm(`Are you sure you want to delete ticket #${id}?`)) {
                    const res = await apiCall('delete_ticket', 'POST', { ticket_id: id });
                    if (res.success) {
                        router();
                    } else {
                        alert('Error: ' + (res.error || 'Failed to delete ticket'));
                    }
                }
                return;
            }

            // Back to list
            if (e.target.closest('.btn-back-to-list')) {
                e.preventDefault();
                navigateTo('index.php?action=dashboard');
                return;
            }

            // Open Create
            if (e.target.closest('#btn-open-create')) {
                e.preventDefault();
                navigateTo('index.php?action=create_ticket');
                return;
            }
        });

        // Delegate User Role changes
        appViewport.addEventListener('change', async (e) => {
            if (e.target.classList.contains('change-user-role')) {
                const userId = e.target.dataset.userId;
                const newRole = e.target.value;
                const res = await apiCall('update_user_role', 'POST', {
                    user_id: userId,
                    role: newRole
                });
                if (res.success) {
                    showUsers();
                } else {
                    alert('Error: ' + (res.error || 'Failed to update role'));
                }
            }
        });
    }

    // Top Navigation Links
    document.querySelectorAll('nav a').forEach(link => {
        if (link.id && link.id.startsWith('nav-')) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const action = link.id.replace('nav-', '');
                navigateTo(`index.php?action=${action}`);
            });
        }
    });

    // Logo link
    const logoLink = document.querySelector('header h1 a');
    if (logoLink) {
        logoLink.addEventListener('click', (e) => {
            e.preventDefault();
            navigateTo('index.php?action=dashboard');
        });
    }

    // Dashboard Filters
    const filterStatus = document.getElementById('filter-status');
    if (filterStatus) {
        filterStatus.addEventListener('change', () => {
            showDashboard();
        });
    }

    // Create Form
    const createForm = document.getElementById('create-ticket-form');
    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(createForm);
            try {
                const response = await apiCall('create_ticket', 'POST', formData);
                if (response.id) {
                    createForm.reset();
                    navigateTo('index.php?action=dashboard');
                } else {
                    alert('Error creating ticket: ' + (response.error || 'Unknown error'));
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred.');
            }
        });
    }

    // Stats Export
    const btnExportCsv = document.getElementById('btn-export-csv');
    if (btnExportCsv) {
        btnExportCsv.addEventListener('click', () => {
            window.location.href = 'api.php?action=export_csv';
        });
    }

    // Initial load
    router();

    /**
     * UI Helper Functions
     */
    function showSection(sectionName) {
        Object.values(sections).forEach(sec => {
            if (sec) sec.classList.add('hidden');
        });
        if (sections[sectionName]) {
            sections[sectionName].classList.remove('hidden');
            window.scrollTo(0, 0);
        }
    }

    async function showDashboard() {
        showSection('dashboard');
        const tableBody = document.getElementById('ticket-table-body');
        if (!tableBody) return;

        tableBody.innerHTML = '<tr><td colspan="8">Loading tickets...</td></tr>';

        try {
            let tickets = await apiCall('get_tickets');
            if (tickets.error) {
                tableBody.innerHTML = `<tr><td colspan="8" class="error">${tickets.error}</td></tr>`;
                return;
            }

            const filterValue = document.getElementById('filter-status').value;
            if (filterValue !== 'all') {
                tickets = tickets.filter(t => t.status === filterValue);
            }

            if (tickets.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8">No tickets found.</td></tr>';
                return;
            }

            tableBody.innerHTML = '';
            tickets.forEach(ticket => {
                const tr = document.createElement('tr');
                let actionsHtml = `<button class="btn btn-sm btn-secondary btn-view-ticket" data-id="${ticket.id}">View</button>`;
                
                if (window.APP_CONFIG.userRole === 'admin') {
                    actionsHtml += ` <button class="btn btn-sm btn-danger btn-delete-ticket" data-id="${ticket.id}">Delete</button>`;
                }

                tr.innerHTML = `
                    <td>${ticket.id}</td>
                    <td><a href="#" class="btn-view-ticket" data-id="${ticket.id}">${escapeHtml(ticket.title)}</a></td>
                    <td><span class="badge badge-${ticket.status}">${ticket.status}</span></td>
                    <td><span class="badge badge-${ticket.priority}">${ticket.priority}</span></td>
                    <td>${escapeHtml(ticket.created_by_name)}</td>
                    <td>${escapeHtml(ticket.assigned_to_name)}</td>
                    <td>${ticket.created_at}</td>
                    <td>${actionsHtml}</td>
                `;
                tableBody.appendChild(tr);
            });
        } catch (err) {
            console.error(err);
            tableBody.innerHTML = '<tr><td colspan="8" class="error">Failed to load tickets.</td></tr>';
        }
    }

    async function showTicketDetails(id) {
        showSection('details');
        try {
            const ticket = await apiCall(`get_ticket&id=${id}`);
            if (ticket.error) {
                alert(ticket.error);
                navigateTo('index.php?action=dashboard');
                return;
            }

            state.currentTicket = ticket;
            renderTicket(ticket);

            if (['admin', 'technician'].includes(window.APP_CONFIG.userRole)) {
                if (state.technicians.length === 0) {
                    state.technicians = await apiCall('get_technicians');
                }
                const assignSelect = document.getElementById('det-assign-user');
                if (assignSelect) {
                    assignSelect.innerHTML = '<option value="">Unassigned</option>';
                    state.technicians.forEach(tech => {
                        const option = document.createElement('option');
                        option.value = tech.id;
                        option.textContent = tech.name;
                        if (ticket.assigned_to == tech.id) option.selected = true;
                        assignSelect.appendChild(option);
                    });
                }
            }
        } catch (err) {
            console.error(err);
            alert('Failed to load ticket details.');
        }
    }

    function renderTicket(ticket) {
        document.getElementById('det-ticket-title').textContent = `#${ticket.id}: ${ticket.title}`;
        document.getElementById('det-description').textContent = ticket.description;
        
        const statusBadge = document.getElementById('det-status-badge');
        statusBadge.textContent = ticket.status;
        statusBadge.className = `badge badge-${ticket.status}`;
        
        const priorityBadge = document.getElementById('det-priority-badge');
        priorityBadge.textContent = ticket.priority;
        priorityBadge.className = `badge badge-${ticket.priority}`;

        document.getElementById('det-creator').textContent = ticket.created_by_name;
        document.getElementById('det-assignee').textContent = ticket.assigned_to_name;
        document.getElementById('det-date').textContent = ticket.created_at;

        const statusSelect = document.getElementById('det-change-status');
        if (statusSelect) statusSelect.value = ticket.status;

        const prioritySelect = document.getElementById('det-change-priority');
        if (prioritySelect) prioritySelect.value = ticket.priority;

        const claimBtn = document.getElementById('det-btn-claim');
        if (claimBtn) {
            const isTechOrAdmin = ['admin', 'technician'].includes(window.APP_CONFIG.userRole);
            const isAssignedToMe = ticket.assigned_to == window.APP_CONFIG.userId;
            if (isTechOrAdmin && !isAssignedToMe) {
                claimBtn.classList.remove('hidden');
            } else {
                claimBtn.classList.add('hidden');
            }
        }

        renderComments(ticket.comments);
        renderFiles(ticket.files);
    }

    function renderComments(comments) {
        const list = document.getElementById('det-comments-list');
        list.innerHTML = '';
        if (comments.length === 0) {
            list.innerHTML = '<p class="muted">No comments yet.</p>';
            return;
        }

        comments.forEach(comment => {
            const div = document.createElement('div');
            div.className = 'comment-item';
            div.innerHTML = `
                <div class="comment-meta">
                    <strong>${escapeHtml(comment.user_name)}</strong> 
                    <span class="muted">${comment.created_at}</span>
                </div>
                <div class="comment-body">${escapeHtml(comment.comment)}</div>
            `;
            list.appendChild(div);
        });
    }

    function renderFiles(files) {
        const list = document.getElementById('det-file-list');
        list.innerHTML = '';
        if (!files || files.length === 0) {
            list.innerHTML = '<p class="muted">No attachments.</p>';
            return;
        }

        files.forEach(file => {
            const div = document.createElement('div');
            div.className = 'file-item';
            div.innerHTML = `<a href="${file.url}" target="_blank"><span>📄</span> ${escapeHtml(file.name)}</a>`;
            list.appendChild(div);
        });
    }

    function setupDetailsListeners() {
        const commentForm = document.getElementById('det-add-comment-form');
        if (commentForm) {
            commentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const textEl = document.getElementById('det-comment-text');
                const text = textEl.value;
                const res = await apiCall('add_comment', 'POST', {
                    ticket_id: state.currentTicket.id,
                    comment: text
                });
                if (res.id) {
                    textEl.value = '';
                    const updatedTicket = await apiCall(`get_ticket&id=${state.currentTicket.id}`);
                    state.currentTicket = updatedTicket;
                    renderComments(updatedTicket.comments);
                } else {
                    alert('Error: ' + (res.error || 'Failed to add comment'));
                }
            });
        }

        const statusSelect = document.getElementById('det-change-status');
        if (statusSelect) {
            statusSelect.addEventListener('change', async () => {
                const newStatus = statusSelect.value;
                const res = await apiCall('update_ticket', 'POST', {
                    ticket_id: state.currentTicket.id,
                    status: newStatus
                });
                if (res.success) {
                    const badge = document.getElementById('det-status-badge');
                    badge.textContent = newStatus;
                    badge.className = `badge badge-${newStatus}`;
                } else {
                    alert('Error: ' + (res.error || 'Failed to update status'));
                }
            });
        }

        const prioritySelect = document.getElementById('det-change-priority');
        if (prioritySelect) {
            prioritySelect.addEventListener('change', async () => {
                const newPriority = prioritySelect.value;
                const res = await apiCall('update_ticket', 'POST', {
                    ticket_id: state.currentTicket.id,
                    priority: newPriority
                });
                if (res.success) {
                    const badge = document.getElementById('det-priority-badge');
                    badge.textContent = newPriority;
                    badge.className = `badge badge-${newPriority}`;
                } else {
                    alert('Error: ' + (res.error || 'Failed to update priority'));
                }
            });
        }

        const assignBtn = document.getElementById('det-btn-assign');
        if (assignBtn) {
            assignBtn.addEventListener('click', async () => {
                const userId = document.getElementById('det-assign-user').value;
                const res = await apiCall('update_ticket', 'POST', {
                    ticket_id: state.currentTicket.id,
                    assigned_to: userId
                });
                if (res.success) {
                    const updatedTicket = await apiCall(`get_ticket&id=${state.currentTicket.id}`);
                    state.currentTicket = updatedTicket;
                    document.getElementById('det-assignee').textContent = updatedTicket.assigned_to_name;
                } else {
                    alert('Error: ' + (res.error || 'Failed to assign ticket'));
                }
            });
        }

        const claimBtn = document.getElementById('det-btn-claim');
        if (claimBtn) {
            claimBtn.addEventListener('click', async () => {
                const res = await apiCall('update_ticket', 'POST', {
                    ticket_id: state.currentTicket.id,
                    assigned_to: window.APP_CONFIG.userId
                });
                if (res.success) {
                    const updatedTicket = await apiCall(`get_ticket&id=${state.currentTicket.id}`);
                    state.currentTicket = updatedTicket;
                    renderTicket(updatedTicket);
                } else {
                    alert('Error: ' + (res.error || 'Failed to claim ticket'));
                }
            });
        }

        const uploadForm = document.getElementById('det-upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fileInput = document.getElementById('det-file-input');
                if (fileInput.files.length === 0) return;

                const formData = new FormData();
                formData.append('ticket_id', state.currentTicket.id);
                formData.append('file', fileInput.files[0]);

                const res = await apiCall('upload_file', 'POST', formData);
                if (res.success) {
                    fileInput.value = '';
                    const updatedTicket = await apiCall(`get_ticket&id=${state.currentTicket.id}`);
                    state.currentTicket = updatedTicket;
                    renderFiles(updatedTicket.files);
                } else {
                    alert('Error: ' + (res.error || 'Failed to upload file'));
                }
            });
        }
    }

    async function showLogs() {
        showSection('logs');
        const tableBody = document.getElementById('logs-table-body');
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="4">Loading logs...</td></tr>';

        try {
            const logs = await apiCall('get_logs');
            if (logs.error) {
                tableBody.innerHTML = `<tr><td colspan="4" class="error">${logs.error}</td></tr>`;
                return;
            }
            tableBody.innerHTML = '';
            logs.forEach(log => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${log.timestamp}</td>
                    <td>${escapeHtml(log.user_name)}</td>
                    <td><code>${escapeHtml(log.action)}</code></td>
                    <td>${escapeHtml(log.details)}</td>
                `;
                tableBody.appendChild(tr);
            });
        } catch (err) {
            console.error(err);
            tableBody.innerHTML = '<tr><td colspan="4" class="error">Failed to load logs.</td></tr>';
        }
    }

    async function showUsers() {
        showSection('users');
        const tableBody = document.getElementById('users-table-body');
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="6">Loading users...</td></tr>';

        try {
            const users = await apiCall('get_users');
            if (users.error) {
                tableBody.innerHTML = `<tr><td colspan="6" class="error">${users.error}</td></tr>`;
                return;
            }
            tableBody.innerHTML = '';
            users.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${u.id}</td>
                    <td>${escapeHtml(u.name)}</td>
                    <td>${escapeHtml(u.username)}</td>
                    <td>${escapeHtml(u.email)}</td>
                    <td><span class="badge badge-secondary">${u.role}</span></td>
                    <td>
                        <select class="change-user-role" data-user-id="${u.id}">
                            <option value="user" ${u.role === 'user' ? 'selected' : ''}>User</option>
                            <option value="technician" ${u.role === 'technician' ? 'selected' : ''}>Technician</option>
                            <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        } catch (err) {
            console.error(err);
            tableBody.innerHTML = '<tr><td colspan="6" class="error">Failed to load users.</td></tr>';
        }
    }

    async function showStats() {
        showSection('stats');
        try {
            const stats = await apiCall('get_stats');
            if (stats.error) {
                alert(stats.error);
                return;
            }
            
            document.getElementById('stats-total').textContent = stats.total;
            
            const statusLabels = Object.keys(stats.status);
            const statusData = Object.values(stats.status);
            
            const priorityLabels = Object.keys(stats.priority);
            const priorityData = Object.values(stats.priority);

            if (state.charts.status) state.charts.status.destroy();
            if (state.charts.priority) state.charts.priority.destroy();

            const ctxStatus = document.getElementById('chart-status').getContext('2d');
            state.charts.status = new Chart(ctxStatus, {
                type: 'pie',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: ['#007bff', '#ffc107', '#28a745', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            const ctxPriority = document.getElementById('chart-priority').getContext('2d');
            state.charts.priority = new Chart(ctxPriority, {
                type: 'pie',
                data: {
                    labels: priorityLabels,
                    datasets: [{
                        data: priorityData,
                        backgroundColor: ['#17a2b8', '#007bff', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            const statusList = document.getElementById('stats-status-list');
            statusList.innerHTML = '';
            for (const [status, count] of Object.entries(stats.status)) {
                const row = document.createElement('div');
                row.className = 'info-row';
                row.innerHTML = `<span class="label">${status}:</span> <span>${count}</span>`;
                statusList.appendChild(row);
            }

            const priorityList = document.getElementById('stats-priority-list');
            priorityList.innerHTML = '';
            for (const [priority, count] of Object.entries(stats.priority)) {
                const row = document.createElement('div');
                row.className = 'info-row';
                row.innerHTML = `<span class="label">${priority}:</span> <span>${count}</span>`;
                priorityList.appendChild(row);
            }
        } catch (err) {
            console.error(err);
            alert('Failed to load statistics.');
        }
    }

    async function apiCall(action, method = 'GET', body = null) {
        const url = `api.php?action=${action}`;
        const options = {
            method,
            cache: 'no-store'
        };

        if (body) {
            if (body instanceof FormData) {
                options.body = body;
            } else {
                const params = new URLSearchParams();
                for (const [key, value] of Object.entries(body)) {
                    params.append(key, value);
                }
                options.body = params;
            }
        }

        const response = await fetch(url, options);
        return await response.json();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    setupDetailsListeners();
});
