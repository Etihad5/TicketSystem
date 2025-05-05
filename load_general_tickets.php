<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    die("غير مصرح بالوصول");
}

$stmt = $conn->query("SELECT id, title, status, priority, created_at FROM tickets 
                      WHERE location IS NULL OR location = '' ORDER BY created_at DESC LIMIT 5");
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($tickets): ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr class="table-light">
                    <th width="10%">#</th>
                    <th width="40%">العنوان</th>
                    <th width="15%">الحالة</th>
                    <th width="15%">الأولوية</th>
                    <th width="20%">التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?php echo $ticket['id']; ?></td>
                    <td>
                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none text-primary">
                            <?php echo htmlspecialchars($ticket['title']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge <?php 
                            echo ($ticket['status'] == 'مكتملة') ? 'bg-success' : 
                                 (($ticket['status'] == 'قيد الانتظار') ? 'bg-primary' : 'bg-danger'); 
                        ?>">
                            <?php echo htmlspecialchars($ticket['status']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php
                            echo ($ticket['priority'] == 'عالية') ? 'bg-danger' :
                                 (($ticket['priority'] == 'متوسطة') ? 'bg-warning text-dark' : 'bg-success');
                        ?>">
                            <?php echo htmlspecialchars($ticket['priority']); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="text-center py-4">
        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
        <p class="text-muted mt-3">لا توجد تذاكر عامة حالياً</p>
    </div>
<?php endif; ?>