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
                // Zkontroluj dostupnost knihy
                $stmt = $conn->prepare("SELECT ks FROM kniha WHERE knihaID = ?");
                $stmt->execute([$knihaID]);
                $kniha = $stmt->fetch();

                if (!$kniha || $kniha['ks'] <= 0) {
                    $error = "Kniha není dostupná (není skladem)!";
                } else {
                    // Začni transakci
                    $conn->beginTransaction();

                    // Sniž počet kusů
                    $stmt = $conn->prepare("UPDATE kniha SET ks = ks - 1 WHERE knihaID = ? AND ks > 0");
                    $stmt->execute([$knihaID]);

                    if ($stmt->rowCount() > 0) {
                        // Vytvoř půjčení
                        $stmt = $conn->prepare("INSERT INTO pujceni (knihaID, zakID, zamestnanecID, delka_cas, datum_pujceni) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$knihaID, $zakID, $zamestnanecID, $delka_cas]);

                        $conn->commit();
                        header("Location: pujceni.php?success=1");
                        exit();
                    } else {
                        $conn->rollBack();
                        $error = "Kniha právě není dostupná!";
                    }
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Chyba při půjčení knihy: " . $e->getMessage());
                $error = "Chyba při půjčování knihy.";
            }
        }
    }
}

// Vrácení knihy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vratit_knihu'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $pujceniID = filter_var($_POST['pujceni_id'] ?? '', FILTER_VALIDATE_INT);

        if ($pujceniID) {
            try {
                // Získat knihaID
                $stmt = $conn->prepare("SELECT knihaID FROM pujceni WHERE pujceniID = ?");
                $stmt->execute([$pujceniID]);
                $pujceni = $stmt->fetch();

                if ($pujceni) {
                    // Začni transakci
                    $conn->beginTransaction();

                    // Zvyš počet kusů
                    $stmt = $conn->prepare("UPDATE kniha SET ks = ks + 1 WHERE knihaID = ?");
                    $stmt->execute([$pujceni['knihaID']]);

                    // Smaž záznam o půjčení
                    $stmt = $conn->prepare("DELETE FROM pujceni WHERE pujceniID = ?");
                    $stmt->execute([$pujceniID]);

                    $conn->commit();
                    header("Location: pujceni.php?returned=1");
                    exit();
                } else {
                    $error = "Půjčení nenalezeno!";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Chyba při vrácení knihy: " . $e->getMessage());
                $error = "Chyba při vracení knihy.";
            }
        }
    }
}

if (isset($_GET['success'])) $success = "Kniha byla úspěšně půjčena!";
if (isset($_GET['returned'])) $success = "Kniha byla úspěšně vrácena!";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <section class="page-header">
            <h2>📋 Správa půjčení</h2>
            <p>Přehled všech aktivních půjčení</p>
        </section>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <button type="button" onclick="openModal('addLoanModal')" class="btn-primary">
                ➕ Nové půjčení
            </button>
        </div>

        <section class="content-section">
            <h3>Aktivní půjčení</h3>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Kniha</th>
                    <th>Žák</th>
                    <th>Třída</th>
                    <th>Zaměstnanec</th>
                    <th>Datum půjčení</th>
                    <th>Vrátit do</th>
                    <th>Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php
                try {
                    $sql = "SELECT p.pujceniID, p.datum_pujceni, p.delka_cas,
                                   k.nazev as kniha_nazev,
                                   CONCAT(z.jmeno, ' ', z.prijmeni) as zak_jmeno,
                                   z.trida,
                                   CONCAT(u.jmeno, ' ', u.prijmeni) as zam_jmeno
                            FROM pujceni p
                            JOIN kniha k ON p.knihaID = k.knihaID
                            JOIN zak_zakaznici z ON p.zakID = z.zakID
                            JOIN ucitel_zamestnanec u ON p.zamestnanecID = u.zamestnanecID
                            ORDER BY p.datum_pujceni DESC";

                    $result = $conn->query($sql);

                    if ($result->rowCount() === 0) {
                        echo "<tr><td colspan='8' style='text-align: center;'>Žádná aktivní půjčení.</td></tr>";
                    } else {
                        while($row = $result->fetch()) {
                            $vraceni_date = new DateTime($row['delka_cas']);
                            $today = new DateTime();
                            $is_overdue = $vraceni_date < $today;

                            echo "<tr" . ($is_overdue ? " class='overdue'" : "") . ">";
                            echo "<td>" . htmlspecialchars($row['pujceniID']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['kniha_nazev']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['zak_jmeno']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['trida']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['zam_jmeno']) . "</td>";
                            echo "<td>" . date('d.m.Y H:i', strtotime($row['datum_pujceni'])) . "</td>";
                            echo "<td>" . date('d.m.Y', strtotime($row['delka_cas'])) . ($is_overdue ? " <span class='badge-overdue'>⚠️ Po termínu</span>" : "") . "</td>";
                            echo "<td class='action-buttons'>
                                    <form method='POST' style='display: inline;' onsubmit='return confirm(\"Potvrdit vrácení knihy?\")'>
                                        <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCSRFToken()) . "'>
                                        <input type='hidden' name='pujceni_id' value='" . htmlspecialchars($row['pujceniID']) . "'>
                                        <button type='submit' name='vratit_knihu' class='btn-success'>✓ Vrátit</button>
                                    </form>
                                  </td>";
                            echo "</tr>";
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Chyba při načítání půjčení: " . $e->getMessage());
                    echo "<tr><td colspan='8' style='text-align: center; color: red;'>Chyba při načítání dat.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal pro nové půjčení -->
    <div id="addLoanModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addLoanModal')">&times;</span>
            <h2>Nové půjčení</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-group">
                    <label for="knihaID">Kniha:</label>
                    <select id="knihaID" name="knihaID" required>
                        <option value="">Vyberte knihu...</option>
                        <?php
                        try {
                            $knihy = $conn->query("SELECT k.knihaID, k.nazev, CONCAT(a.jmeno, ' ', a.prijmeni) as autor, k.ks 
                                                   FROM kniha k 
                                                   JOIN autor a ON k.autorID = a.autorID 
                                                   WHERE k.ks > 0 
                                                   ORDER BY k.nazev");
                            while($kniha = $knihy->fetch()) {
                                echo "<option value='" . $kniha['knihaID'] . "'>" .
                                    htmlspecialchars($kniha['nazev']) . " - " .
                                    htmlspecialchars($kniha['autor']) .
                                    " (Skladem: " . $kniha['ks'] . ")</option>";
                            }
                        } catch (PDOException $e) {
                            echo "<option value=''>Chyba načítání knih</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="zakID">Žák:</label>
                    <select id="zakID" name="zakID" required>
                        <option value="">Vyberte žáka...</option>
                        <?php
                        try {
                            $zaci = $conn->query("SELECT zakID, jmeno, prijmeni, trida FROM zak_zakaznici ORDER BY prijmeni, jmeno");
                            while($zak = $zaci->fetch()) {
                                echo "<option value='" . $zak['zakID'] . "'>" .
                                    htmlspecialchars($zak['prijmeni']) . " " .
                                    htmlspecialchars($zak['jmeno']) .
                                    " (" . htmlspecialchars($zak['trida']) . ")</option>";
                            }
                        } catch (PDOException $e) {
                            echo "<option value=''>Chyba načítání žáků</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="zamestnanecID">Zaměstnanec:</label>
                    <select id="zamestnanecID" name="zamestnanecID" required>
                        <option value="">Vyberte zaměstnance...</option>
                        <?php
                        try {
                            $zamestnanci = $conn->query("SELECT zamestnanecID, jmeno, prijmeni FROM ucitel_zamestnanec ORDER BY prijmeni, jmeno");
                            while($zam = $zamestnanci->fetch()) {
                                echo "<option value='" . $zam['zamestnanecID'] . "'>" .
                                    htmlspecialchars($zam['prijmeni']) . " " .
                                    htmlspecialchars($zam['jmeno']) . "</option>";
                            }
                        } catch (PDOException $e) {
                            echo "<option value=''>Chyba načítání zaměstnanců</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="delka_cas">Vrátit do:</label>
                    <input type="date" id="delka_cas" name="delka_cas" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                </div>
                <button type="submit" name="pujcit" class="btn-primary">Půjčit knihu</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Virtuální Knihovna | Školní knihovní systém</p>
    </footer>
</div>

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = "block";
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = "none";
    }

    window.onclick = function(event) {
        const modal = document.getElementById('addLoanModal');
        if (event.target === modal) modal.style.display = "none";
    }

    if (window.location.search.includes('success=') || window.location.search.includes('returned=')) {
        setTimeout(function() {
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 3000);
    }
</script>
</body>
</html>