<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once 'config.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Nové půjčení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pujcit'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $knihaID = filter_var($_POST['knihaID'] ?? '', FILTER_VALIDATE_INT);
        $zakID = filter_var($_POST['zakID'] ?? '', FILTER_VALIDATE_INT);
        $zamestnanecID = filter_var($_POST['zamestnanecID'] ?? '', FILTER_VALIDATE_INT);
        $delka_cas = sanitizeInput($_POST['delka_cas'] ?? '');

        if (!$knihaID || !$zakID || !$zamestnanecID || empty($delka_cas)) {
            $error = "Všechna pole musí být vyplněna!";
        } else {
            try {
                $stmt = $conn->prepare("SELECT ks FROM kniha WHERE knihaID = ?");
                $stmt->execute([$knihaID]);
                $kniha = $stmt->fetch();

                if (!$kniha || $kniha['ks'] <= 0) {
                    $error = "Kniha není dostupná skladem!";
                } else {
                    $conn->beginTransaction();
                    $stmt = $conn->prepare("UPDATE kniha SET ks = ks - 1 WHERE knihaID = ? AND ks > 0");
                    $stmt->execute([$knihaID]);

                    $stmt = $conn->prepare("INSERT INTO pujceni (knihaID, zakID, zamestnanecID, delka_cas) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$knihaID, $zakID, $zamestnanecID, $delka_cas]);
                    $conn->commit();

                    header("Location: pujceni.php?success=1");
                    exit();
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = "Chyba při půjčování: " . $e->getMessage();
            }
        }
    }
}

// Vrácení knihy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vratit_knihu'])) {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        $pujceniID = filter_var($_POST['pujceni_id'] ?? '', FILTER_VALIDATE_INT);
        try {
            $stmt = $conn->prepare("SELECT knihaID FROM pujceni WHERE pujceniID = ?");
            $stmt->execute([$pujceniID]);
            $pujceni = $stmt->fetch();
            if ($pujceni) {
                $conn->beginTransaction();
                $conn->prepare("UPDATE kniha SET ks = ks + 1 WHERE knihaID = ?")->execute([$pujceni['knihaID']]);
                $conn->prepare("DELETE FROM pujceni WHERE pujceniID = ?")->execute([$pujceniID]);
                $conn->commit();
                header("Location: pujceni.php?returned=1");
                exit();
            }
        } catch (PDOException $e) { if ($conn->inTransaction()) $conn->rollBack(); }
    }
}

if (isset($_GET['success'])) $success = "Kniha byla půjčena!";
if (isset($_GET['returned'])) $success = "Kniha byla vrácena!";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Půjčení - Virtuální Knihovna</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <h1>📚 Virtuální Knihovna</h1>
        <nav>
            <a href="main.php">Domů</a>
            <a href="knihy.php">Knihy</a>
            <a href="pujceni.php" class="active">Půjčení</a>
            <a href="zakaznici.php">Zákazníci</a>
            <a href="zamestnanci.php">Zaměstnanci</a>
        </nav>
    </header>
    <main>
        <h2>📋 Správa půjčení</h2>
        <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <button type="button" onclick="document.getElementById('addLoanModal').style.display='block'" class="btn-primary">➕ Nové půjčení</button>

        <table>
            <thead><tr><th>ID</th><th>Kniha</th><th>Žák</th><th>Vrátit do</th><th>Akce</th></tr></thead>
            <tbody>
            <?php
            $sql = "SELECT p.pujceniID, p.delka_cas, k.nazev, CONCAT(z.jmeno, ' ', z.prijmeni) as zak_jmeno 
                    FROM pujceni p JOIN kniha k ON p.knihaID = k.knihaID JOIN zak_zakaznici z ON p.zakID = z.zakID";
            $res = $conn->query($sql);
            while($row = $res->fetch()) {
                echo "<tr>";
                echo "<td>".$row['pujceniID']."</td><td>".$row['nazev']."</td><td>".$row['zak_jmeno']."</td><td>".$row['delka_cas']."</td>";
                echo "<td><form method='POST'><input type='hidden' name='csrf_token' value='".generateCSRFToken()."'><input type='hidden' name='pujceni_id' value='".$row['pujceniID']."'><button type='submit' name='vratit_knihu' class='btn-success'>✓ Vrátit</button></form></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </main>

    <div id="addLoanModal" class="modal" style="display:none; position:fixed; background:rgba(0,0,0,0.5); width:100%; height:100%; top:0; left:0;">
        <div class="modal-content" style="background:#fff; margin:10% auto; padding:20px; width:400px;">
            <h2>Nové půjčení</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <label>Kniha:</label>
                <select name="knihaID" required><option value="">Vyberte...</option>
                    <?php
                    $knihy = $conn->query("SELECT knihaID, nazev, ks FROM kniha WHERE ks > 0");
                    while($k = $knihy->fetch()) echo "<option value='".$k['knihaID']."'>".$k['nazev']." (".$k['ks']."ks)</option>";
                    ?>
                </select><br><br>
                <label>Žák:</label>
                <select name="zakID" required><option value="">Vyberte...</option>
                    <?php
                    $zaci = $conn->query("SELECT zakID, prijmeni FROM zak_zakaznici");
                    while($z = $zaci->fetch()) echo "<option value='".$z['zakID']."'>".$z['prijmeni']."</option>";
                    ?>
                </select><br><br>
                <label>Zaměstnanec:</label>
                <select name="zamestnanecID" required><option value="">Vyberte...</option>
                    <?php
                    $zam = $conn->query("SELECT zamestnanecID, prijmeni FROM ucitel_zamestnanec");
                    while($z = $zam->fetch()) echo "<option value='".$z['zamestnanecID']."'>".$z['prijmeni']."</option>";
                    ?>
                </select><br><br>
                <label>Vrátit do:</label>
                <input type="date" name="delka_cas" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required><br><br>
                <button type="submit" name="pujcit" class="btn-primary">Půjčit</button>
                <button type="button" onclick="document.getElementById('addLoanModal').style.display='none'">Zavřít</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>