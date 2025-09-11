<?php
// Start session to store historical race data
session_start();

// Initialize session array for historical race data 
if (!isset($_SESSION['race_data'])) {
    $_SESSION['race_data'] = [];
}

// <---- Functions ---->

// This calculate the current average speed (km/h)
function calcCurrentSpeed($covered, $elapsed) {
    return ($elapsed > 0) ? $covered / $elapsed : 0;
}

// This calculate the required speed (km/h) to finish the marathon in target time
function calcRequiredSpeed($total, $covered, $elapsed, $target) {
    $remainingDistance = $total - $covered;
    $remainingTime = $target - $elapsed;

    if ($remainingTime > 0) {
        return $remainingDistance / $remainingTime;
    } else {
        return "No time left. You already lost the race."; // display this message if the runner has no time left
    }
}

// Save record to external file 
function saveData($filename, $record) {
    $line = implode(",", $record) . PHP_EOL;
    file_put_contents($filename, $line, FILE_APPEND);
}

// Handle Form Submission 
$error = "";

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Delete runner 
    if (isset($_POST['delete_index'])) {
        $deleteIndex = intval($_POST['delete_index']);
        if (isset($_SESSION['race_data'][$deleteIndex])) {
            // Remove the selected runner from session
            array_splice($_SESSION['race_data'], $deleteIndex, 1);
            // Redirect to avoid form resubmission on refresh
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } else { 
        //  Add new runner 
        $name    = trim($_POST['name'] ?? "");
        $total   = $_POST['total'] ?? 0;
        $covered = $_POST['covered'] ?? 0;
        $elapsed = $_POST['elapsed'] ?? 0;
        $target  = $_POST['target'] ?? 0;

        // Validate inputs
        if (empty($name) || !is_numeric($total) || !is_numeric($covered) || 
            !is_numeric($elapsed) || !is_numeric($target) ||
            $total <= 0 || $covered < 0 || $elapsed <= 0 || $target <= 0 || $covered > $total) {
            $error = "❌ Invalid input. Please enter a name and positive numbers. 
                       Distance covered must not exceed total distance.";
        } else {
            // Calculate speeds
            $currentSpeed = calcCurrentSpeed($covered, $elapsed);
            $requiredSpeed = calcRequiredSpeed($total, $covered, $elapsed, $target);

            // Prepare record to store
            $record = [
                $name, 
                $total, 
                $covered, 
                $elapsed, 
                $target, 
                number_format($currentSpeed, 2), 
                is_numeric($requiredSpeed) ? number_format($requiredSpeed, 2) : $requiredSpeed
            ];

            // Add record to session array
            $_SESSION['race_data'][] = $record;

            // Save record to file
            saveData("race_data.txt", $record);

            // Redirect to avoid duplicate submission when the user refreshes the page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Runner Progress Tracker</title>
    <style>
        /*  body styling */
        body { 
            font-family: Arial, sans-serif; 
            margin: 80px; 
            color: white; 
            background: url('bg_img.jpg') no-repeat  ;
            background-color: darkblue; 
            background-size: cover;
        }

        /* Container styling */
        .container-narrow {
            border-radius: 20px;
            background: rgba(0, 0, 0, .2);
            backdrop-filter: blur(20px);
            box-shadow: 0 0 30px rgba(0, 0, 0, .3);
            border: 2px solid rgba(255, 255, 255, .2);
            color: white;
            max-width: 1100px;
            padding: 20px 30px;
            margin: 0 auto;
        }

        /* Header styling */
        h1 {
            text-align: center;
            margin-bottom: 40px;
            font-size: 32px;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 40px;
            margin-bottom: 30px;
            font-size: 20px;
        }

        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input {
            background-color: transparent;
            outline: none;
            border: 2px solid rgba(255, 255, 255, .2);
            padding: 15px;
            width: 100%;
            border-radius: 40px;
            font-size: 16px;
            color: white;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        input:focus {
            border: 2px solid rgba(0, 110, 200, 1);
            box-shadow: 0 0 10px rgba(0, 110, 200, 0.7);
        }

        button {
            padding: 12px 25px;
            margin-top: 10px;
            cursor: pointer;
            border-radius: 40px;
            border: none;
            background: rgba(0, 255, 0, 0.6);
            font-weight: bold;
            font-size: 16px;
            color: black;
            transition: all 0.3s ease;
            font-size: 20px;
        }

        button:hover {
            background: rgba(0, 255, 0, 0.9);
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.6);
        }

        .error { 
            color: #ff6b6b; 
            margin-bottom: 20px; 
            grid-column: span 2;
            text-align: center;
            font-weight: bold;
            font-size: 20px;
        }

        .table-container { 
            max-height: 500px; 
            overflow-y: auto; 
            margin-top: 20px; 
        }

        table { 
            border-collapse: collapse; 
            width: 100%; 
            text-align: center;
        }

        th, td { 
            border: 1px solid #ccc; 
            padding: 10px; 
        }

        th { 
            background: rgba(0, 110, 200, 1); 
            color: white;
            position: sticky; 
            top: 0; 
            z-index: 2; 
        }

        /* Subtle glow animation for newest row */
        @keyframes flash {
            0% { background-color: rgba(0, 255, 0, 0.9); }
            50% { background-color: rgba(0, 255, 0, 0.1); }
            100% { background-color: rgba(0, 255, 0, 0.9); }
        }
        .highlight-new {
            animation: flash 6s ease-in-out;
        }

        /* Highlight for topmost row */
        .highlight {
            background-color: rgba(1, 143, 72, 0.92); 
        }

        .delete-btn { 
            background: #ff4c4c; 
            border: none; 
            color: white; 
            padding: 5px 10px; 
            border-radius: 5px; 
            cursor: pointer; 
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background: #ff1a1a;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }

        /* Responsive layout for small screens */
        @media (max-width: 700px) {
            form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>


<body>
    <div class="container-narrow">
        <h1>🏃 Runner Progress Tracker</h1>

        <!-- Runner input form -->
        <form method="POST">
            <div>
                <label>Runner's Name:</label>
                <input type="text" name="name" required>
            </div>
            <div>
                <label>Total Marathon Distance (km):</label>
                <input type="text" name="total" required>
            </div>
            <div>
                <label>Distance Covered (km):</label>
                <input type="text" name="covered" required>
            </div>
            <div>
                <label>Elapsed Time (hours):</label>
                <input type="text" name="elapsed" required>
            </div>
            <div>
                <label>Target Time (hours):</label>
                <input type="text" name="target" required>
            </div>
            <button type="submit">Calculate</button>
        </form>

        <!-- Display error messages -->
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>

        <h2> Historical Race Data</h2>
        <div class="table-container">
            <table>
                <tr>
                    <th>Runner</th>
                    <th>Total Distance (km)</th>
                    <th>Distance Covered (km)</th>
                    <th>Elapsed Time (hrs)</th>
                    <th>Target Time (hrs)</th>
                    <th>Current Speed (km/h)</th>
                    <th>Required Speed (km/h)</th>
                    <th>Action</th>
                </tr>


                <?php 
                // Display the latest entry at the top
                $reversedData = array_reverse($_SESSION['race_data']);
                $totalRows = count($_SESSION['race_data']);
                foreach ($reversedData as $index => $row): 
                    $originalIndex = $totalRows - $index - 1;
                    // Add highlight-new only to the newest row
                    $rowClass = ($index === 0) ? 'highlight highlight-new' : '';
                ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <?php foreach ($row as $col): ?>
                            <td><?php echo htmlspecialchars($col); ?></td>
                        <?php endforeach; ?>
                        <td>
                            <!-- Delete form -->
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="delete_index" value="<?php echo $originalIndex; ?>">
                                <button type="submit" class="delete-btn" onclick="return confirm('Are you sure you want to delete this runner?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>
