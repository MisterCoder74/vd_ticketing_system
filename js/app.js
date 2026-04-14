document.addEventListener('DOMContentLoaded', () => {
    const action = document.body.dataset.action;
    const ticketId = document.body.dataset.ticketId;

    if (action === 'dashboard') {
        initDashboard();
    } else if (action === 'view_ticket' && ticketId) {
        initTicketDetails(ticketId);
    }

    // Toggle Create Form
    const showCreateBtn = document.getElementById('show-create-form');
    const hideCreateBtn = document.getElementById('hide-create-form');
    const createSection = document.getElementById('create-ticket-section');

    if (showCreateBtn && createSection) {
        showCreateBtn.addEventListener('click', () => {
            createSection.classList.remove('hidden');
        });
    }

    if (hideCreateBtn && createSection) {
        hideCreateBtn.addEventListener('click', () => {
            createSection.classList.add('hidden');
        });
    }

    // Handle Create Ticket
    const createForm = document.getElementById('create-ticket-form');
    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(createForm);
            try {
                const response = await apiCall('create_ticket', 'POST', formData);
                if (response.id) {
                    alert('Ticket created successfully!');
                    window.location.reload();
                } else {
                    alert('Error creating ticket: ' + (response.error || 'Unknown error'));
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred.');
            }
        });
    }
});

/**
 * Centralized API Call helper
 */
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
    const data = await response.json();
    return data;
}

/**
 * Dashboard Logic
 */
async function initDashboard() {
    const tableBody = document.getElementById('ticket-table-body');
    if (!tableBody) return;

    try {
        const tickets = await apiCall('get_tickets');
        if (tickets.error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="error">${tickets.error}</td></tr>`;
            return;
        }

        if (tickets.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8">No tickets found.</td></tr>';
            return;
        }

        tableBody.innerHTML = '';
        tickets.forEach(ticket => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${ticket.id}</td>
                <td><a href="index.php?action=view_ticket&id=${ticket.id}">${escapeHtml(ticket.title)}</a></td>
                <td><span class="badge badge-${ticket.status}">${ticket.status}</span></td>
                <td><span class="badge badge-${ticket.priority}">${ticket.priority}</span></td>
                <td>${escapeHtml(ticket.created_by_name)}</td>
                <td>${escapeHtml(ticket.assigned_to_name)}</td>
                <td>${ticket.created_at}</td>
                <td><a href="index.php?action=view_ticket&id=${ticket.id}" class="btn btn-sm">View</a></td>
            `;
            tableBody.appendChild(tr);
        });
    } catch (err) {
        console.error(err);
        tableBody.innerHTML = '<tr><td colspan="8" class="error">Failed to load tickets.</td></tr>';
    }
}

/**
 * Ticket Details Logic
 */
async function initTicketDetails(id) {
    try {
        const ticket = await apiCall(`get_ticket&id=${id}`);
        if (ticket.error) {
            document.getElementById('ticket-title-display').textContent = 'Error';
            document.getElementById('ticket-description-display').textContent = ticket.error;
            return;
        }

        renderTicket(ticket);

        // Populate technicians if admin or tech
        if (['admin', 'technician'].includes(window.APP_CONFIG.userRole)) {
            const technicians = await apiCall('get_technicians');
            const assignSelect = document.getElementById('assign-user-select');
            if (assignSelect) {
                technicians.forEach(tech => {
                    const option = document.createElement('option');
                    option.value = tech.id;
                    option.textContent = tech.name;
                    if (ticket.assigned_to == tech.id) option.selected = true;
                    assignSelect.appendChild(option);
                });
            }
        }

        // Setup Event Listeners for Details Page
        setupDetailsListeners(ticket);

    } catch (err) {
        console.error(err);
        alert('Failed to load ticket details.');
    }
}

function renderTicket(ticket) {
    document.getElementById('ticket-title-display').textContent = `#${ticket.id}: ${ticket.title}`;
    document.getElementById('ticket-description-display').textContent = ticket.description;
    
    const statusBadge = document.getElementById('ticket-status-badge');
    statusBadge.textContent = ticket.status;
    statusBadge.className = `badge badge-${ticket.status}`;
    
    const priorityBadge = document.getElementById('ticket-priority-badge');
    priorityBadge.textContent = ticket.priority;
    priorityBadge.className = `badge badge-${ticket.priority}`;

    document.getElementById('ticket-creator').textContent = ticket.created_by_name;
    document.getElementById('ticket-assignee').textContent = ticket.assigned_to_name;
    document.getElementById('ticket-date').textContent = ticket.created_at;

    const statusSelect = document.getElementById('change-status-select');
    if (statusSelect) statusSelect.value = ticket.status;

    // Render Comments
    renderComments(ticket.comments);
    
    // Render Files
    renderFiles(ticket.files);
}

function renderComments(comments) {
    const list = document.getElementById('comments-list');
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
                <span class="muted">at ${comment.created_at}</span>
            </div>
            <div class="comment-body">${escapeHtml(comment.comment)}</div>
        `;
        list.appendChild(div);
    });
}

function renderFiles(files) {
    const list = document.getElementById('file-list');
    list.innerHTML = '';
    if (!files || files.length === 0) {
        list.innerHTML = '<p class="muted">No attachments.</p>';
        return;
    }

    files.forEach(file => {
        const div = document.createElement('div');
        div.className = 'file-item';
        div.innerHTML = `<a href="${file.url}" target="_blank">${escapeHtml(file.name)}</a>`;
        list.appendChild(div);
    });
}

function setupDetailsListeners(ticket) {
    // Comment Form
    const commentForm = document.getElementById('add-comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = document.getElementById('comment-text').value;
            const res = await apiCall('add_comment', 'POST', {
                ticket_id: ticket.id,
                comment: text
            });
            if (res.id) {
                document.getElementById('comment-text').value = '';
                // Refresh comments
                const updatedTicket = await apiCall(`get_ticket&id=${ticket.id}`);
                renderComments(updatedTicket.comments);
            } else {
                alert('Error: ' + (res.error || 'Failed to add comment'));
            }
        });
    }

    // Status Change
    const statusSelect = document.getElementById('change-status-select');
    if (statusSelect) {
        statusSelect.addEventListener('change', async () => {
            const newStatus = statusSelect.value;
            const res = await apiCall('update_status', 'POST', {
                ticket_id: ticket.id,
                status: newStatus
            });
            if (res.success) {
                const badge = document.getElementById('ticket-status-badge');
                badge.textContent = newStatus;
                badge.className = `badge badge-${newStatus}`;
            } else {
                alert('Error: ' + (res.error || 'Failed to update status'));
            }
        });
    }

    // Assignment
    const assignBtn = document.getElementById('btn-assign');
    if (assignBtn) {
        assignBtn.addEventListener('click', async () => {
            const userId = document.getElementById('assign-user-select').value;
            const res = await apiCall('assign_ticket', 'POST', {
                ticket_id: ticket.id,
                assigned_to: userId
            });
            if (res.success) {
                alert('Ticket assigned successfully');
                const updatedTicket = await apiCall(`get_ticket&id=${ticket.id}`);
                document.getElementById('ticket-assignee').textContent = updatedTicket.assigned_to_name;
            } else {
                alert('Error: ' + (res.error || 'Failed to assign ticket'));
            }
        });
    }

    // Upload
    const uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fileInput = document.getElementById('file-input');
            if (fileInput.files.length === 0) return;

            const formData = new FormData();
            formData.append('ticket_id', ticket.id);
            formData.append('file', fileInput.files[0]);

            const res = await apiCall('upload_file', 'POST', formData);
            if (res.success) {
                fileInput.value = '';
                const updatedTicket = await apiCall(`get_ticket&id=${ticket.id}`);
                renderFiles(updatedTicket.files);
            } else {
                alert('Error: ' + (res.error || 'Failed to upload file'));
            }
        });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
