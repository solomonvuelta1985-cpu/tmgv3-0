<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication and check session timeout
require_login();
check_session_timeout();

// Get dashboard statistics
$stats = [
    'today_citations' => 0,
    'today_trend' => 0,
    'pending_citations' => 0,
    'pending_trend' => 0,
    'resolved_week' => 0,
    'resolved_trend' => 0,
    'overdue_citations' => 0,
    'overdue_trend' => 0,
    'active_officers' => 0
];

$recent_citations = [];
$user_first_name = 'Officer';
$this_month = 0;
$last_month = 0;
$monthly_trend = 0;

$pdo = getPDO();
if ($pdo) {
    try {
        // Today's citations
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE DATE(created_at) = CURDATE()");
        $result = $stmt->fetch();
        $stats['today_citations'] = $result['count'] ?? 0;

        // Yesterday's citations for trend
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
        $result = $stmt->fetch();
        $yesterday = $result['count'] ?? 0;
        if ($yesterday > 0) {
            $stats['today_trend'] = round((($stats['today_citations'] - $yesterday) / $yesterday) * 100);
        }

        // Pending citations
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE status = 'pending'");
        $result = $stmt->fetch();
        $stats['pending_citations'] = $result['count'] ?? 0;

        // Resolved this week
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE status = 'paid' AND WEEK(created_at) = WEEK(CURDATE())");
        $result = $stmt->fetch();
        $stats['resolved_week'] = $result['count'] ?? 0;

        // Resolved last week for trend
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE status = 'paid' AND WEEK(created_at) = WEEK(CURDATE()) - 1");
        $result = $stmt->fetch();
        $last_week = $result['count'] ?? 0;
        if ($last_week > 0) {
            $stats['resolved_trend'] = round((($stats['resolved_week'] - $last_week) / $last_week) * 100);
        }

        // Overdue citations
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE status = 'pending' AND DATEDIFF(CURDATE(), created_at) > 30");
        $result = $stmt->fetch();
        $stats['overdue_citations'] = $result['count'] ?? 0;

        // Active users from database
        $stmt = db_query("SELECT COUNT(*) as count FROM users");
        $result = $stmt ? $stmt->fetch() : null;
        $stats['active_officers'] = $result['count'] ?? 0;

        // Weekly citations data (last 7 days for bar chart)
        $weekly_citations = [];
        $weekly_labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $day_label = date('D', strtotime("-$i days"));
            $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE DATE(created_at) = ?", [$date]);
            $result = $stmt ? $stmt->fetch() : null;
            $weekly_citations[] = $result['count'] ?? 0;
            $weekly_labels[] = $day_label;
        }

        // Status distribution for doughnut chart
        $status_distribution = [];
        $status_labels = ['Pending', 'Paid', 'Contested', 'Dismissed'];
        foreach (['pending', 'paid', 'contested', 'dismissed'] as $status) {
            $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE status = ?", [$status]);
            $result = $stmt ? $stmt->fetch() : null;
            $status_distribution[] = $result['count'] ?? 0;
        }

        // Top 5 violations (last 30 days)
        $top_violations = [];
        $stmt = db_query("
            SELECT vt.violation_type as name, COUNT(*) as count
            FROM citations c
            JOIN violations v ON c.citation_id = v.citation_id
            JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY vt.violation_type
            ORDER BY count DESC
            LIMIT 5
        ");
        if ($stmt) {
            $top_violations = $stmt->fetchAll();
        }

        // Monthly comparison
        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        $result = $stmt ? $stmt->fetch() : null;
        $this_month = $result['count'] ?? 0;

        $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)");
        $result = $stmt ? $stmt->fetch() : null;
        $last_month = $result['count'] ?? 0;
        $monthly_trend = $last_month > 0 ? round((($this_month - $last_month) / $last_month) * 100) : 0;

        // Extract first name for personalization
        $user_first_name = explode(' ', $_SESSION['full_name'] ?? 'Officer')[0];

        // Get recent citations with violation names
        $stmt = db_query("
            SELECT
                c.citation_id,
                c.ticket_number,
                c.license_number,
                c.status,
                c.created_at,
                GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violation_name
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            GROUP BY c.citation_id
            ORDER BY c.created_at DESC
            LIMIT 5
        ");
        if ($stmt) {
            $recent_citations = $stmt->fetchAll();
            // Set default violation name if none found
            foreach ($recent_citations as $key => $citation) {
                if (empty($citation['violation_name'])) {
                    $recent_citations[$key]['violation_name'] = 'Unknown';
                }
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Traffic Citation System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Colors - Purple Theme Preserved */
            --color-primary: #9155fd;
            --color-primary-light: #b389ff;
            --color-primary-dark: #7367f0;
            --color-success: #56ca00;
            --color-warning: #ffb400;
            --color-danger: #ff4c51;
            --color-info: #16b1ff;

            /* Backgrounds */
            --bg-primary: #f5f5f9;
            --bg-card: #ffffff;
            --bg-surface: #fafafa;

            /* Text */
            --text-primary: #185593;
            --text-secondary: #185593;
            --text-disabled: #185593;

            /* Borders */
            --border-color: #dbdade;
            --divider-color: #e7e7e9;

            /* Material Design Elevation Shadows */
            --shadow-1: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            --shadow-2: 0 3px 6px rgba(0, 0, 0, 0.15), 0 2px 4px rgba(0, 0, 0, 0.12);
            --shadow-3: 0 10px 20px rgba(0, 0, 0, 0.15), 0 3px 6px rgba(0, 0, 0, 0.10);
            --shadow-4: 0 15px 25px rgba(0, 0, 0, 0.15), 0 5px 10px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 8px 16px rgba(145, 85, 253, 0.2);

            /* Spacing (8px grid) */
            --spacing-1: 8px;
            --spacing-2: 16px;
            --spacing-3: 24px;
            --spacing-4: 32px;

            /* Border Radius */
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-full: 9999px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-size: clamp(14px, 1.5vw, 15px);
            overflow-x: hidden;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }

        .main-container {
            flex: 1;
            margin-left: 280px;
            margin-top: 64px;
            padding: 1rem 1.5rem;
            width: calc(100% - 280px);
            min-height: calc(100vh - 64px);
            box-sizing: border-box;
            max-width: calc(100vw - 280px);
            overflow-x: hidden;
            overflow-y: auto;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0, 0.2, 1), max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-container > * {
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Sidebar Collapsed State */
        .sidebar-collapsed .main-container {
            margin-left: 72px;
            width: calc(100% - 72px);
            max-width: calc(100vw - 72px);
        }

        /* Hero Welcome Card */
        .hero-card {
            background: linear-gradient(135deg, #9155fd 0%, #7367f0 100%);
            border-radius: var(--radius-lg);
            padding: clamp(1rem, 3vw, 1.5rem);
            margin-bottom: var(--spacing-2);
            box-shadow: var(--shadow-3);
            color: white;
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-2);
            position: relative;
            z-index: 1;
            min-width: 0;
            gap: var(--spacing-2);
        }

        .hero-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: var(--spacing-2);
            backdrop-filter: blur(10px);
        }

        .hero-title {
            font-size: clamp(1.25rem, 3vw, 1.75rem);
            font-weight: 700;
            margin-bottom: var(--spacing-1);
            letter-spacing: -0.02em;
        }

        .hero-subtitle {
            font-size: clamp(0.875rem, 1.8vw, 0.9375rem);
            opacity: 0.95;
        }

        .hero-icon-circle {
            width: clamp(80px, 15vw, 120px);
            height: clamp(80px, 15vw, 120px);
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .hero-cta-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-1);
            background: white;
            color: var(--color-primary);
            padding: 12px 28px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9375rem;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }

        .hero-cta-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            color: var(--color-primary);
        }

        .hero-meta-bar {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-2);
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
        }

        .hero-date-display {
            display: flex;
            align-items: center;
            gap: var(--spacing-1);
            color: white;
            font-size: clamp(0.75rem, 1.5vw, 0.875rem);
            padding: 8px 14px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-sm);
            backdrop-filter: blur(10px);
        }

        .hero-notification-btn {
            position: relative;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
            color: white;
        }

        .hero-notification-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .hero-notification-btn .count {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--color-danger);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: var(--radius-full);
            min-width: 20px;
            text-align: center;
        }

        /* Top Actions Bar */
        .top-actions-bar {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-2);
            flex-wrap: wrap;
            width: 100%;
            box-sizing: border-box;
        }

        .search-input {
            position: relative;
            flex: 1;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        .search-input input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.9375rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: var(--bg-card);
            box-sizing: border-box;
        }

        .search-input input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(145, 85, 253, 0.1);
        }

        .search-input i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: var(--spacing-1);
            color: var(--text-secondary);
            font-size: 0.875rem;
            padding: 12px 16px;
            background: var(--bg-card);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-1);
        }

        .notification-btn {
            position: relative;
            width: 44px;
            height: 44px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: var(--shadow-1);
        }

        .notification-btn:hover {
            border-color: var(--color-primary);
            background: rgba(145, 85, 253, 0.04);
        }

        .notification-btn .count {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--color-danger);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: var(--radius-full);
            min-width: 20px;
            text-align: center;
        }

        /* Compact Metrics */
        .compact-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-2);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .compact-metrics-grid > * {
            min-width: 0;
            max-width: 100%;
        }

        .compact-metric-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 12px;
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            box-shadow: var(--shadow-1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            box-sizing: border-box;
            min-width: 0;
            overflow: hidden;
        }

        .compact-metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-2);
            border-color: var(--border-color);
        }

        .metric-icon-wrapper {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .metric-icon-wrapper.primary { background: rgba(145, 85, 253, 0.12); color: var(--color-primary); }
        .metric-icon-wrapper.success { background: rgba(86, 202, 0, 0.12); color: var(--color-success); }
        .metric-icon-wrapper.warning { background: rgba(255, 180, 0, 0.12); color: var(--color-warning); }
        .metric-icon-wrapper.danger { background: rgba(255, 76, 81, 0.12); color: var(--color-danger); }
        .metric-icon-wrapper.info { background: rgba(22, 177, 255, 0.12); color: var(--color-info); }

        .metric-number {
            font-size: clamp(1.25rem, 2.5vw, 1.5rem);
            font-weight: 700;
            color: #185593;
            line-height: 1.2;
        }

        .metric-data {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .metric-label {
            font-size: clamp(0.75rem, 1.5vw, 0.8125rem);
            color: var(--text-secondary);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .metric-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .metric-trend.up { color: var(--color-success); }
        .metric-trend.down { color: var(--color-danger); }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-2);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .charts-grid > * {
            min-width: 0;
            max-width: 100%;
        }

        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 12px;
            box-shadow: var(--shadow-1);
            transition: all 0.3s ease;
            box-sizing: border-box;
            min-width: 0;
            overflow: hidden;
        }

        .chart-card:hover {
            box-shadow: var(--shadow-2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-2);
        }

        .card-title {
            font-size: clamp(1rem, 2vw, 1.125rem);
            font-weight: 600;
            color: #185593;
            display: flex;
            align-items: center;
            gap: var(--spacing-1);
            margin-bottom: 4px;
        }

        .card-subtitle {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .chart-summary {
            text-align: right;
        }

        .summary-value {
            font-size: clamp(1.5rem, 3vw, 1.75rem);
            font-weight: 700;
            color: var(--color-primary);
            line-height: 1;
        }

        .summary-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .chart-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        .chart-container canvas {
            max-width: 100%;
            height: auto !important;
        }

        /* Violations List */
        .violations-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        .violation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            background: var(--bg-surface);
            border-radius: var(--radius-sm);
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .violation-item:hover {
            background: var(--bg-primary);
            transform: translateX(4px);
        }

        .violation-info {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
        }

        .violation-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(145, 85, 253, 0.1);
            color: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .violation-name {
            font-size: clamp(0.875rem, 1.8vw, 0.9375rem);
            font-weight: 600;
            color: #185593;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            min-width: 0;
        }

        .violation-stats {
            display: flex;
            align-items: center;
            gap: var(--spacing-1);
        }

        .violation-count {
            font-size: clamp(1rem, 2vw, 1.125rem);
            font-weight: 700;
            color: var(--text-primary);
        }

        .violation-badge {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* Activity Timeline */
        .activity-timeline-compact {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        .timeline-item-compact {
            display: flex;
            gap: var(--spacing-2);
            padding: 8px;
            background: var(--bg-surface);
            border-radius: var(--radius-sm);
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .timeline-item-compact:hover {
            background: var(--bg-primary);
            transform: translateX(4px);
        }

        .timeline-dot-compact {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .timeline-dot-compact.primary { background: var(--color-primary); color: white; }
        .timeline-dot-compact.success { background: var(--color-success); color: white; }
        .timeline-dot-compact.warning { background: var(--color-warning); color: white; }

        .timeline-content-compact {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .timeline-header h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .timeline-badge {
            padding: 3px 10px;
            border-radius: var(--radius-sm);
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .timeline-badge.pending { background: rgba(255, 180, 0, 0.12); color: var(--color-warning); }
        .timeline-badge.paid { background: rgba(86, 202, 0, 0.12); color: var(--color-success); }

        .timeline-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .timeline-time {
            font-size: 0.75rem;
            color: var(--text-disabled);
        }

        /* Card Footer */
        .card-footer {
            margin-top: var(--spacing-2);
            padding-top: 8px;
            border-top: 1px solid var(--divider-color);
        }

        .footer-link {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-1);
            color: var(--color-primary);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .footer-link:hover {
            gap: var(--spacing-2);
        }

        /* Quick Actions */
        .quick-actions-section {
            margin-bottom: var(--spacing-2);
            width: 100%;
            box-sizing: border-box;
        }

        .section-heading {
            font-size: clamp(1rem, 2vw, 1.125rem);
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-1);
            margin-bottom: var(--spacing-2);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr));
            gap: var(--spacing-2);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .quick-actions-grid > * {
            min-width: 0;
            max-width: 100%;
        }

        .action-card-compact {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            padding: 12px;
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow-1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            box-sizing: border-box;
            min-width: 0;
            overflow: hidden;
        }

        .action-card-compact:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-3);
            border-color: var(--color-primary);
        }

        .action-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .action-icon.primary { background: var(--color-primary); }
        .action-icon.info { background: var(--color-info); }
        .action-icon.warning { background: var(--color-warning); }
        .action-icon.success { background: var(--color-success); }

        .action-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .action-text h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            color:#185593;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .action-text p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .action-arrow {
            color: var(--text-secondary);
            transition: all 0.2s;
        }

        .action-card-compact:hover .action-arrow {
            transform: translateX(4px);
            color: var(--color-primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--spacing-4);
            color: var(--text-secondary);
        }

        .empty-state i {
            margin-bottom: var(--spacing-2);
            opacity: 0.4;
        }

        .empty-state p {
            margin: 0;
        }

        /* Monthly Comparison Card */
        .monthly-card {
            grid-column: 1 / -1;
            box-sizing: border-box;
        }

        .monthly-stats {
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: var(--spacing-2) 0;
            width: 100%;
            box-sizing: border-box;
            flex-wrap: wrap;
            gap: var(--spacing-2);
        }

        .monthly-stat-item {
            text-align: center;
            box-sizing: border-box;
        }

        .monthly-stat-value {
            font-size: clamp(1.5rem, 3.5vw, 2rem);
            font-weight: 700;
            color: #185593;
        }

        .monthly-stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .monthly-trend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 1.125rem;
            font-weight: 600;
            margin-top: var(--spacing-1);
        }

        .monthly-trend-indicator.positive { color: var(--color-success); }
        .monthly-trend-indicator.negative { color: var(--color-danger); }

        /* Responsive */
        @media (max-width: 1280px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                margin-left: 0 !important;
                margin-top: 70px;
                padding: 1rem 1rem;
                width: 100% !important;
                min-height: calc(100vh - 70px);
                max-width: 100vw !important;
                overflow-x: hidden;
            }

            /* Override collapsed state on mobile */
            .sidebar-collapsed .main-container {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw !important;
            }

            .hero-content {
                flex-direction: column;
                text-align: center;
                gap: var(--spacing-2);
            }

            .hero-meta-bar {
                justify-content: center;
            }

            .hero-icon-circle {
                width: 80px;
                height: 80px;
            }

            .compact-metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-1);
            }

            .compact-metric-card {
                padding: var(--spacing-1);
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .top-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: 100%;
                width: 100%;
            }

            .chart-card {
                padding: var(--spacing-2);
            }
        }

        @media (max-width: 480px) {
            .main-container {
                margin-left: 0 !important;
                margin-top: 70px;
                padding: 0.75rem;
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden;
            }

            /* Override collapsed state on small mobile */
            .sidebar-collapsed .main-container {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw !important;
            }

            .compact-metrics-grid {
                grid-template-columns: 1fr;
            }

            .hero-icon-circle {
                width: 60px;
                height: 60px;
            }

            .hero-cta-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/loader.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="dashboard-wrapper">
        <div class="main-container">
            <?php echo show_flash(); ?>

            <!-- Hero Welcome Card -->
            <div class="hero-card">
                <div class="hero-meta-bar">
                    <div class="hero-date-display">
                        <i data-lucide="calendar" width="18" height="18"></i>
                        <span id="current-date"></span>
                    </div>
                    <div class="hero-notification-btn">
                        <i data-lucide="bell" width="20" height="20"></i>
                        <?php if ($stats['pending_citations'] > 0): ?>
                            <span class="count"><?php echo $stats['pending_citations']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hero-content">
                    <div class="hero-text">
                        <div class="hero-badge">
                            <i data-lucide="sparkles" width="14" height="14"></i>
                            Well done!
                        </div>
                        <h2 class="hero-title">
                            Congratulations <?php echo htmlspecialchars($user_first_name); ?>! 🎉
                        </h2>
                        <p class="hero-subtitle">
                            You've processed <strong><?php echo $stats['resolved_week']; ?> citations</strong> this week
                        </p>
                    </div>
                    <div class="hero-illustration">
                        <div class="hero-icon-circle">
                            <i data-lucide="trophy" width="48" height="48"></i>
                        </div>
                    </div>
                </div>
                <a href="index2.php" class="hero-cta-btn">
                    <i data-lucide="plus-circle" width="18" height="18"></i>
                    Create Citation
                </a>
            </div>

            <!-- Top Actions Bar -->
            <div class="top-actions-bar">
                <form action="search.php" method="GET" class="search-input">
                    <i data-lucide="search" width="20" height="20"></i>
                    <input type="text" name="q" placeholder="Search citations, tickets..." />
                </form>
            </div>

            <!-- Compact Metrics Grid -->
            <div class="compact-metrics-grid">
                <!-- Today's Citations -->
                <div class="compact-metric-card">
                    <div class="metric-icon-wrapper primary">
                        <i data-lucide="file-text" width="24" height="24"></i>
                    </div>
                    <div class="metric-data">
                        <div class="metric-number"><?php echo $stats['today_citations']; ?></div>
                        <div class="metric-label">Today's Citations</div>
                        <?php if ($stats['today_trend'] != 0): ?>
                            <div class="metric-trend <?php echo $stats['today_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i data-lucide="<?php echo $stats['today_trend'] > 0 ? 'trending-up' : 'trending-down'; ?>" width="12" height="12"></i>
                                <?php echo abs($stats['today_trend']); ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Citations -->
                <div class="compact-metric-card">
                    <div class="metric-icon-wrapper warning">
                        <i data-lucide="clock" width="24" height="24"></i>
                    </div>
                    <div class="metric-data">
                        <div class="metric-number"><?php echo $stats['pending_citations']; ?></div>
                        <div class="metric-label">Pending Citations</div>
                    </div>
                </div>

                <!-- Resolved This Week -->
                <div class="compact-metric-card">
                    <div class="metric-icon-wrapper success">
                        <i data-lucide="check-circle" width="24" height="24"></i>
                    </div>
                    <div class="metric-data">
                        <div class="metric-number"><?php echo $stats['resolved_week']; ?></div>
                        <div class="metric-label">Resolved This Week</div>
                        <?php if ($stats['resolved_trend'] != 0): ?>
                            <div class="metric-trend <?php echo $stats['resolved_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i data-lucide="<?php echo $stats['resolved_trend'] > 0 ? 'trending-up' : 'trending-down'; ?>" width="12" height="12"></i>
                                <?php echo abs($stats['resolved_trend']); ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Overdue Citations -->
                <div class="compact-metric-card">
                    <div class="metric-icon-wrapper danger">
                        <i data-lucide="alert-triangle" width="24" height="24"></i>
                    </div>
                    <div class="metric-data">
                        <div class="metric-number"><?php echo $stats['overdue_citations']; ?></div>
                        <div class="metric-label">Overdue Citations</div>
                    </div>
                </div>

                <!-- Users -->
                <div class="compact-metric-card">
                    <div class="metric-icon-wrapper info">
                        <i data-lucide="users" width="24" height="24"></i>
                    </div>
                    <div class="metric-data">
                        <div class="metric-number"><?php echo $stats['active_officers']; ?></div>
                        <div class="metric-label">Users</div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics Grid -->
            <div class="charts-grid">
                <!-- Weekly Overview Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                <i data-lucide="bar-chart-3" width="20" height="20"></i>
                                Weekly Overview
                            </h3>
                            <p class="card-subtitle">Citations in the last 7 days</p>
                        </div>
                        <div class="chart-summary">
                            <div class="summary-value"><?php echo array_sum($weekly_citations); ?></div>
                            <div class="summary-label">Total</div>
                        </div>
                    </div>
                    <div class="chart-container" style="height: 200px;">
                        <canvas id="weeklyOverviewChart"></canvas>
                    </div>
                </div>

                <!-- Status Distribution Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                <i data-lucide="pie-chart" width="20" height="20"></i>
                                Status Distribution
                            </h3>
                            <p class="card-subtitle">Citation status breakdown</p>
                        </div>
                    </div>
                    <div class="chart-container" style="height: 200px;">
                        <canvas id="statusBreakdownChart"></canvas>
                    </div>
                </div>

                <!-- Top Violations -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i data-lucide="list" width="20" height="20"></i>
                            Top Violations
                        </h3>
                    </div>
                    <div class="violations-list">
                        <?php if (!empty($top_violations)): ?>
                            <?php foreach ($top_violations as $violation): ?>
                                <div class="violation-item">
                                    <div class="violation-info">
                                        <div class="violation-icon">
                                            <i data-lucide="alert-circle" width="16" height="16"></i>
                                        </div>
                                        <div class="violation-name"><?php echo htmlspecialchars($violation['name']); ?></div>
                                    </div>
                                    <div class="violation-stats">
                                        <span class="violation-count"><?php echo $violation['count']; ?></span>
                                        <span class="violation-badge">citations</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i data-lucide="inbox" width="48" height="48"></i>
                                <p>No violation data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="citations.php" class="footer-link">
                            View All Citations
                            <i data-lucide="arrow-right" width="16" height="16"></i>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i data-lucide="activity" width="20" height="20"></i>
                            Recent Activity
                        </h3>
                    </div>
                    <div class="activity-timeline-compact">
                        <?php if (!empty($recent_citations)): ?>
                            <?php foreach ($recent_citations as $citation): ?>
                                <div class="timeline-item-compact">
                                    <div class="timeline-dot-compact <?php echo $citation['status'] == 'paid' ? 'success' : ($citation['status'] == 'pending' ? 'warning' : 'primary'); ?>">
                                        <i data-lucide="<?php echo $citation['status'] == 'paid' ? 'check' : 'file-text'; ?>" width="12" height="12"></i>
                                    </div>
                                    <div class="timeline-content-compact">
                                        <div class="timeline-header">
                                            <h4>Citation #<?php echo htmlspecialchars($citation['ticket_number'] ?? 'N/A'); ?></h4>
                                            <span class="timeline-badge <?php echo $citation['status']; ?>">
                                                <?php echo strtoupper($citation['status']); ?>
                                            </span>
                                        </div>
                                        <p class="timeline-text">
                                            <?php echo htmlspecialchars($citation['violation_name'] ?? 'Unknown'); ?>
                                            <?php if (isset($citation['license_number']) && $citation['license_number']): ?>
                                                • <?php echo htmlspecialchars($citation['license_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <div class="timeline-time">
                                            <?php
                                            $time = strtotime($citation['created_at']);
                                            $diff = time() - $time;
                                            if ($diff < 3600) {
                                                echo floor($diff / 60) . ' min ago';
                                            } elseif ($diff < 86400) {
                                                echo floor($diff / 3600) . ' hours ago';
                                            } else {
                                                echo date('M j, Y', $time);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i data-lucide="inbox" width="32" height="32"></i>
                                <p>No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="citations.php" class="footer-link">
                            View All
                            <i data-lucide="arrow-right" width="16" height="16"></i>
                        </a>
                    </div>
                </div>

                <!-- Monthly Comparison Card -->
                <div class="chart-card monthly-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i data-lucide="trending-up" width="20" height="20"></i>
                            Monthly Comparison
                        </h3>
                    </div>
                    <div class="monthly-stats">
                        <div class="monthly-stat-item">
                            <div class="monthly-stat-value"><?php echo $this_month; ?></div>
                            <div class="monthly-stat-label">This Month</div>
                        </div>
                        <div class="monthly-stat-item">
                            <div class="monthly-stat-value"><?php echo $last_month; ?></div>
                            <div class="monthly-stat-label">Last Month</div>
                        </div>
                        <div class="monthly-stat-item">
                            <div class="monthly-trend-indicator <?php echo $monthly_trend >= 0 ? 'positive' : 'negative'; ?>">
                                <i data-lucide="<?php echo $monthly_trend >= 0 ? 'trending-up' : 'trending-down'; ?>" width="18" height="18"></i>
                                <?php echo abs($monthly_trend); ?>%
                            </div>
                            <div class="monthly-stat-label">Trend</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="quick-actions-section">
                <h3 class="section-heading">
                    <i data-lucide="zap" width="20" height="20"></i>
                    Quick Actions
                </h3>
                <div class="quick-actions-grid">
                    <a href="index2.php" class="action-card-compact">
                        <div class="action-icon primary">
                            <i data-lucide="plus-circle" width="24" height="24"></i>
                        </div>
                        <div class="action-text">
                            <h4>New Citation</h4>
                            <p>Create and issue</p>
                        </div>
                        <i data-lucide="arrow-right" width="18" height="18" class="action-arrow"></i>
                    </a>

                    <a href="search.php" class="action-card-compact">
                        <div class="action-icon info">
                            <i data-lucide="search" width="24" height="24"></i>
                        </div>
                        <div class="action-text">
                            <h4>Search Records</h4>
                            <p>Find citations</p>
                        </div>
                        <i data-lucide="arrow-right" width="18" height="18" class="action-arrow"></i>
                    </a>

                    <a href="citations.php" class="action-card-compact">
                        <div class="action-icon success">
                            <i data-lucide="list" width="24" height="24"></i>
                        </div>
                        <div class="action-text">
                            <h4>View All</h4>
                            <p>Browse citations</p>
                        </div>
                        <i data-lucide="arrow-right" width="18" height="18" class="action-arrow"></i>
                    </a>

                    <a href="reports.php" class="action-card-compact">
                        <div class="action-icon warning">
                            <i data-lucide="bar-chart-3" width="24" height="24"></i>
                        </div>
                        <div class="action-text">
                            <h4>Reports</h4>
                            <p>View analytics</p>
                        </div>
                        <i data-lucide="arrow-right" width="18" height="18" class="action-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Update date
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 60000);

        // Initialize Lucide icons
        lucide.createIcons();

        // Chart.js Defaults
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#a8aaae';

        // Weekly Overview Chart
        const weeklyData = <?php echo json_encode($weekly_citations); ?>;
        const weeklyLabels = <?php echo json_encode($weekly_labels); ?>;

        const weeklyCtx = document.getElementById('weeklyOverviewChart');
        if (weeklyCtx) {
            new Chart(weeklyCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: weeklyLabels,
                    datasets: [{
                        label: 'Citations',
                        data: weeklyData,
                        backgroundColor: '#9155fd',
                        borderRadius: 8,
                        barThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#4c4e64',
                            padding: 12,
                            cornerRadius: 8,
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f0f0f0',
                                drawBorder: false
                            },
                            ticks: {
                                stepSize: 1,
                                font: { size: 12 },
                                padding: 8
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 12 },
                                padding: 8
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        // Status Breakdown Chart
        const statusData = <?php echo json_encode($status_distribution); ?>;
        const statusLabels = <?php echo json_encode($status_labels); ?>;

        const statusCtx = document.getElementById('statusBreakdownChart');
        if (statusCtx) {
            new Chart(statusCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: ['#ffb400', '#56ca00', '#ff4c51', '#9ca3af'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 16,
                                font: { size: 12, weight: '500' },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#4c4e64',
                            padding: 12,
                            cornerRadius: 8,
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12 }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }
    </script>
</body>
</html>
