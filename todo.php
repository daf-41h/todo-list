<?php
// Koneksi database
$conn = mysqli_connect("localhost", "root", "", "todolist_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $task = mysqli_real_escape_string($conn, $_POST['task']);
        $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($parent_id) {
            $sql = "INSERT INTO tasks (task, parent_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $task, $parent_id);
        } else {
            $sql = "INSERT INTO tasks (task) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $task);
        }
        $stmt->execute();
    }

    if (isset($_POST['complete'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE tasks SET status = NOT status WHERE id = ? OR parent_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
    }

    if (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM tasks WHERE id = ? OR parent_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $id);
        $stmt->execute();
    }

    if (isset($_POST['edit'])) {
        $id = (int)$_POST['id'];
        $task = mysqli_real_escape_string($conn, $_POST['edited_task']);
        $description = mysqli_real_escape_string($conn, $_POST['edited_description']);
        $sql = "UPDATE tasks SET task = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $task, $description, $id);
        $stmt->execute();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fungsi untuk mendapatkan tasks beserta sub-tasks
function getTasks($parent_id = null)
{
    global $conn;
    if ($parent_id === null) {
        $sql = "SELECT * FROM tasks WHERE parent_id IS NULL ORDER BY created_at ASC";
        $stmt = $conn->prepare($sql);
    } else {
        $sql = "SELECT * FROM tasks WHERE parent_id = ? ORDER BY created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $parent_id);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Fungsi untuk render task dan sub-tasks secara rekursif
function renderTask($task, $level = 0)
{
    global $conn;
    $padding = $level * 4;
    $taskId = $task['id'];
?>
    <div class="task-item pl-<?php echo $padding; ?> mb-4 <?php echo $level > 0 ? 'border-l-2 border-gray-200' : ''; ?>">
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <!-- Status Checkbox -->
                    <form method="POST" class="inline">
                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                        <button type="submit" name="complete"
                            class="w-6 h-6 border-2 rounded-full flex items-center justify-center
                            <?php echo $task['status'] ? 'bg-green-500 border-green-500' : 'border-gray-300'; ?>">
                            <?php if ($task['status']): ?>
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            <?php endif; ?>
                        </button>
                    </form>

                    <!-- Collapse Toggle Button -->
                    <?php
                    $has_subtasks = mysqli_num_rows(getTasks($task['id'])) > 0;
                    if ($has_subtasks):
                    ?>
                        <button onclick="toggleSubtasks(<?php echo $taskId; ?>)"
                            class="collapse-toggle w-6 h-6 flex items-center justify-center transition-transform duration-200"
                            data-task-id="<?php echo $taskId; ?>">
                            <svg class="w-4 h-4 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    <?php endif; ?>

                    <!-- Task Text -->
                    <span class="<?php echo $task['status'] ? 'line-through text-gray-500' : ''; ?>">
                        <?php echo htmlspecialchars($task['task']); ?>
                    </span>
                </div>

                <div class="flex gap-2">
                    <!-- Add Subtask Button -->
                    <button onclick="addSubtask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['task']); ?>')"
                        class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                        + Sub-tugas
                    </button>

                    <!-- Edit Button -->
                    <button onclick="editTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['task']); ?>', '<?php echo htmlspecialchars($task['description'] ?? ''); ?>')"
                        class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors">
                        Edit
                    </button>

                    <!-- Delete Button -->
                    <form method="POST" class="inline">
                        <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                        <button type="submit" name="delete"
                            class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                            onclick="return confirm('Yakin ingin menghapus? Semua sub-tugas juga akan terhapus.')">
                            Hapus
                        </button>
                    </form>
                </div>
            </div>

            <!-- Description Text (if exists) -->
            <?php if (!empty($task['description'])): ?>
                <div class="mt-2 ml-16 text-sm text-gray-600">
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Subtasks Container -->
        <div class="mt-4 subtasks-container transition-all duration-200 ease-in-out"
            data-parent-id="<?php echo $taskId; ?>">
            <?php
            $sub_tasks = getTasks($task['id']);
            while ($sub_task = $sub_tasks->fetch_assoc()) {
                renderTask($sub_task, $level + 1);
            }
            ?>
        </div>
    </div>
<?php
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Todo List Berlapis</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .subtasks-container.collapsed {
            display: none;
        }

        .collapse-toggle.collapsed svg {
            transform: rotate(-90deg);
        }

        .task-item {
            transition: all 0.2s ease-in-out;
        }

        .subtasks-container {
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Aplikasi Todo List Berlapis</h1>

        <!-- Form Tambah Tugas Utama -->
        <form method="POST" class="mb-8">
            <div class="flex gap-2">
                <input type="text" name="task" required
                    class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                    placeholder="Masukkan tugas baru...">
                <button type="submit" name="add"
                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                    Tambah
                </button>
            </div>
        </form>

        <!-- Daftar Tugas -->
        <div class="space-y-4">
            <?php
            $main_tasks = getTasks();
            while ($task = $main_tasks->fetch_assoc()) {
                renderTask($task);
            }
            ?>
        </div>
    </div>

    <!-- Modal Add Subtask -->
    <div id="subtaskModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-96">
            <h2 class="text-xl font-bold mb-4">Tambah Sub-tugas</h2>
            <form method="POST">
                <input type="hidden" name="parent_id" id="parentId">
                <p class="mb-4 text-gray-600">Menambah sub-tugas untuk: <span id="parentTask" class="font-medium"></span></p>
                <input type="text" name="task" required
                    class="w-full px-4 py-2 border rounded-lg mb-4 focus:outline-none focus:border-blue-500"
                    placeholder="Masukkan sub-tugas...">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeSubtaskModal()"
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="add"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                        Tambah
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-96">
            <h2 class="text-xl font-bold mb-4">Edit Tugas</h2>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="editInput">
                        Judul Tugas
                    </label>
                    <input type="text" name="edited_task" id="editInput" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="editDescription">
                        Deskripsi
                    </label>
                    <textarea name="edited_description" id="editDescription"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                        rows="3"
                        placeholder="Tambahkan deskripsi (opsional)..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                        Batal
                    </button>
                    <button type="submit" name="edit"
                        class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load collapsed state from localStorage on page load
            const collapsedStates = JSON.parse(localStorage.getItem('collapsedTasks') || '{}');

            // Apply collapsed states
            Object.entries(collapsedStates).forEach(([taskId, isCollapsed]) => {
                if (isCollapsed) {
                    const container = document.querySelector(`[data-parent-id="${taskId}"]`);
                    const toggle = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (container && toggle) {
                        container.classList.add('collapsed');
                        toggle.classList.add('collapsed');
                    }
                }
            });

            // Restore scroll position if page was reloaded
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            if (scrollPosition && performance.navigation.type === 1) {
                // Check if it's a page reload (type 1)
                window.scrollTo(0, parseInt(scrollPosition));
            } else {
                // If it's a new page visit, scroll to top
                window.scrollTo(0, 0);
                sessionStorage.removeItem('scrollPosition');
            }
        });

        // Save scroll position before page reload
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem('scrollPosition', window.scrollY.toString());
        });

        // Toggle subtasks visibility
        function toggleSubtasks(taskId) {
            const container = document.querySelector(`[data-parent-id="${taskId}"]`);
            const toggle = document.querySelector(`[data-task-id="${taskId}"]`);

            if (container && toggle) {
                container.classList.toggle('collapsed');
                toggle.classList.toggle('collapsed');

                // Save state to localStorage
                const collapsedStates = JSON.parse(localStorage.getItem('collapsedTasks') || '{}');
                collapsedStates[taskId] = container.classList.contains('collapsed');
                localStorage.setItem('collapsedTasks', JSON.stringify(collapsedStates));
            }
        }

        // Modal functions
        function addSubtask(id, task) {
            document.getElementById('subtaskModal').classList.remove('hidden');
            document.getElementById('parentId').value = id;
            document.getElementById('parentTask').textContent = task;
        }

        function closeSubtaskModal() {
            document.getElementById('subtaskModal').classList.add('hidden');
        }

        function editTask(id, task, description = '') {
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editId').value = id;
            document.getElementById('editInput').value = task;
            document.getElementById('editDescription').value = description;
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const subtaskModal = document.getElementById('subtaskModal');
            const editModal = document.getElementById('editModal');

            if (event.target === subtaskModal) {
                closeSubtaskModal();
            }

            if (event.target === editModal) {
                closeEditModal();
            }
        });

        // Handle ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSubtaskModal();
                closeEditModal();
            }
        });

        // Prevent modal close when clicking inside modal content
        document.querySelectorAll('.modal-content').forEach(modal => {
            modal.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });

        // Handle form submissions with validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(event) {
                const taskInput = this.querySelector('input[name="task"], input[name="edited_task"]');
                if (taskInput && taskInput.value.trim() === '') {
                    event.preventDefault();
                    alert('Tugas tidak boleh kosong!');
                }
            });
        });

        // Initialize tooltips for buttons
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip absolute bg-gray-800 text-white px-2 py-1 rounded text-sm';
                tooltip.textContent = this.dataset.tooltip;
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.top = `${rect.bottom + 5}px`;
                tooltip.style.left = `${rect.left + (rect.width - tooltip.offsetWidth) / 2}px`;
            });

            element.addEventListener('mouseleave', function() {
                document.querySelectorAll('.tooltip').forEach(t => t.remove());
            });
        });a
    </script>
</body>

</html>