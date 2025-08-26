<?php
// Set timezone ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Get test time from command line argument
$test_time = isset($argv[1]) ? $argv[1] : null;

echo "🕐 Timezone Test\n";
echo "================\n";

if ($test_time) {
    echo "Testing time: $test_time\n";
    $current_time = $test_time;
} else {
    echo "Current time: " . date('H:i') . "\n";
    $current_time = date('H:i');
}

echo "Current date: " . date('Y-m-d H:i:s') . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n\n";

// Test schedule times
$check_in_time = '08:00';
$check_in_reminder = '07:30';
$late_time = '08:15';
$check_out_time = '17:00';
$check_out_reminder = '16:30';

echo "📅 Schedule Times:\n";
echo "Check-in reminder: $check_in_reminder\n";
echo "Check-in time: $check_in_time\n";
echo "Late time: $late_time\n";
echo "Check-out reminder: $check_out_reminder\n";
echo "Check-out time: $check_out_time\n\n";

echo "🔍 Status for time: $current_time\n";
if ($current_time === $check_in_reminder) {
    echo "✅ It's check-in reminder time! (07:30)\n";
    echo "📱 Would send: Reminder Check-in notifications\n";
} elseif ($current_time === $check_in_time) {
    echo "✅ It's check-in time! (08:00)\n";
    echo "📱 Would send: Check-in notifications\n";
} elseif ($current_time === $late_time) {
    echo "✅ It's late notification time! (08:15)\n";
    echo "📱 Would send: Late notifications\n";
} elseif ($current_time === $check_out_reminder) {
    echo "✅ It's check-out reminder time! (16:30)\n";
    echo "📱 Would send: Check-out reminder notifications\n";
} elseif ($current_time === $check_out_time) {
    echo "✅ It's check-out time! (17:00)\n";
    echo "📱 Would send: Check-out notifications\n";
} else {
    echo "⏰ Not notification time yet\n";
}

echo "\n💡 Usage:\n";
echo "  php test_timezone.php          # Test current time\n";
echo "  php test_timezone.php 07:30    # Test specific time\n";
echo "  php test_timezone.php 08:00    # Test check-in time\n";
echo "  php test_timezone.php 08:15    # Test late time\n";
?>
