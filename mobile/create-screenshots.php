<?php
/**
 * Crea screenshot placeholder per PWA
 */

$screenshotDir = __DIR__ . '/screenshots';
if (!is_dir($screenshotDir)) {
    mkdir($screenshotDir, 0755, true);
}

// Screenshot placeholder in base64 (540x720)
$screenshotData = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAhwAAALQCAYAAADTdDv8AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAOxAAADsQBlSsOGwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAABKFSURBVHic7d17rG1lYYfx72HOOQeQiwKCiIAIKCIgCAiIgIAICAiIgIiAiIAIiAiIgIiAiIAIiAiIgIiAiIAIiAiIgIiAiIAIiAiIgIiAiIAIiAiIcM45' .
    'M/1jd5LZJLNJds/6vvWt9/klhISEnHXW+t71rL332XsAAAAAAAAAAAAAAAAAAAAAAAD/P+PHjx8/fvz48ePHjx8/fvz48ePHjx8/fvz48ePHjx8/fvz48ePHjx8/fvz48ePHjx8/fvz48QAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AABgzI0bN27cuHHjxo0bN27cuHHjxo0bN27cuHHjxo0bN27cuHHjxo0bN27cuHHjxo0bN27cuHHjAMBYGDdu3Lhx48aNGzdu3Lhx48aNGzdu3Lhx48aNGzdu3Lhx48aNGzdu3Lhx48aNGzdu3LhxAGBMjBs3bty4cePGjRs3bty4cePGjRs3bty4cePGjRs3bty4cePGjRs3bty4cePGjRs3btwA' .
    'AAAAAAAAAAA='
);

// Crea dashboard screenshot
file_put_contents($screenshotDir . '/dashboard.png', $screenshotData);

// Crea documents screenshot (stesso placeholder per ora)
file_put_contents($screenshotDir . '/documents.png', $screenshotData);

echo "✅ Screenshot placeholder creati in /screenshots/\n";
echo "   - dashboard.png (540x720)\n";
echo "   - documents.png (540x720)\n";
echo "\nIn produzione, sostituisci con screenshot reali dell'app.\n";