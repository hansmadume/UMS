<?php
$auditLogs = [];
$auditLogError = '';

function auditLogPageEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function auditLogPageIsoDateTime(string $value): string
{
    if ($value === '') {
        return '';
    }

    try {
        $timezone = new DateTimeZone(date_default_timezone_get());
        $dateTime = new DateTimeImmutable($value, $timezone);

        return $dateTime->format(DateTimeInterface::ATOM);
    } catch (Throwable $exception) {
        return '';
    }
}

try {
    $pdo = getDatabaseConnection();

    if (function_exists('authEnsureAuditLogTable')) {
        authEnsureAuditLogTable($pdo);
    }

    $statement = $pdo->query(
        'SELECT id, user_id, user_name, action, ip_address, created_at
         FROM audit_logs
         ORDER BY created_at DESC, id DESC
         LIMIT 200'
    );
    $auditLogs = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Audit log page load failed: ' . $exception->getMessage());
    $auditLogError = 'Audit logs are temporarily unavailable.';
}
?>

<div class="audit-logs">
    <div class="section-header">
        <h2>Audit Logs</h2>
    </div>

    <?php if ($auditLogError !== ''): ?>
        <div class="login-alert login-alert-error" role="alert">
            <?php echo auditLogPageEscape($auditLogError); ?>
        </div>
    <?php endif; ?>

    <div class="mui-table-container">
        <table class="mui-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($auditLogs)): ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <?php
                            $createdAt = (string) ($log['created_at'] ?? '');
                            $createdAtIso = auditLogPageIsoDateTime($createdAt);
                            ?>
                            <td>
                                <time
                                    class="user-local-time"
                                    datetime="<?php echo auditLogPageEscape($createdAtIso); ?>"
                                    data-local-time="<?php echo auditLogPageEscape($createdAtIso); ?>"
                                >
                                    <?php echo auditLogPageEscape(userManagementDisplayDate($createdAt)); ?>
                                </time>
                            </td>
                            <td>
                                <?php echo auditLogPageEscape((string) ($log['user_name'] ?? 'Guest')); ?>
                                <?php if (!empty($log['user_id'])): ?>
                                    <span class="text-muted">#<?php echo (int) $log['user_id']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo auditLogPageEscape((string) ($log['action'] ?? '')); ?></td>
                            <td><?php echo auditLogPageEscape((string) ($log['ip_address'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No audit log records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>