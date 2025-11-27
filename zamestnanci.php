<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once 'config.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Přidání nového zaměstnance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pridat_zamestnance'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $jmeno = sanitizeInput($_POST['jmeno'] ?? '');
        $prijmeni = sanitizeInput($_POST['prijmeni'] ?? '');
        $kabinet = sanitizeInput($_POST['kabinet'] ?? '');

        if (empty($jmeno) || empty($prijmeni) || empty($kabinet)) {
            $error = "Všechna pole musí být vyplněna!";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO ucitel_zamestnanec (jmeno, prijmeni, kabinet) VALUES (?, ?, ?)");
                $stmt->execute([$jmeno, $prijmeni, $kabinet]);

                header("Location: zamestnanci.php?success=1");
                exit();
            } catch (PDOException $e) {
                error_log("Chyba při přidání zaměstnance: " . $e->getMessage());
                $error = "Chyba při přidávání zaměstnance.";
            }
        }
    }
}

// Smazání zaměstnance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smazat_zamestnance'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $zamestnanecID = filter_var($_POST['zamestnanec_id'] ?? '', FILTER_VALIDATE_INT);

        if ($zamestnanecID) {
            try {
                // Zkontroluj, zda nemá zaměstnanec aktivní půjčení
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pujceni WHERE zamestnanecID = ?");
                $stmt->execute([$zamestnanecID]);
                $pujceni = $stmt->fetch()['count'];

                if ($pujceni > 0) {
                    $error = "Zaměstnance nelze smazat, má aktivní půjčení!";
                } else {
                    $stmt = $conn->prepare("DELETE FROM ucitel_zamestnanec WHERE zamestnanecID = ?");
                    $stmt->execute([$zamestnanecID]);

                    header("Location: zamestnanci.php?deleted=1");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Chyba při mazání zaměstnance: " . $e->getMessage());
                $error = "Chyba při mazání zaměstnance.";
            }
        }
    }
}

// Vyhledávání
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$searchParam = "%" . $search . "%";

if (isset($_GET['success'])) $success = "Zaměstnanec byl úspěšně přidán!";
if (isset($_GET['deleted'])) $success = "Zaměstnanec byl úspěšně smazán!";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaměstnanci - Virtuální Knihovna</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <h1>📚 Virtuální Knihovna</h1>
        <nav>
            <a href="main.php">Domů</a>
            <a href="knihy.php">Knihy</a>
            <a href="pujceni.php">Půjčení</a>
            <a href="zakaznici.php">Zákazníci</a>
            <a href="zamestnanci.php" class="active">Zaměstnanci</a>
        </nav>
    </header>

    <main>
        <section class="page-header">
            <h2>👔 Správa zaměstnanců</h2>
            <p>Přehled všech zaměstnanců knihovny</p>
        </section>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <form method="GET" class="search-form">
                <label for="search" class="sr-only">Hledat zaměstnance</label>
                <input type="text" id="search" name="search" placeholder="Hledat zaměstnance..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Hledat</button>
            </form>
            <button type="button" onclick="openModal('addEmployeeModal')" class="btn-primary">
                ➕ Přidat zaměstnance
            </button>
        </div>

        <section class="content-section">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Jméno</th>
                    <th>Příjmení</th>
                    <th>Kabinet</th>
                    <th>Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php
                try {
                    if ($search !== '') {
                        $stmt = $conn->prepare("SELECT * FROM ucitel_zamestnanec WHERE jmeno LIKE ? OR prijmeni LIKE ? OR kabinet LIKE ? ORDER BY prijmeni, jmeno");
                        $stmt->execute([$searchParam, $searchParam, $searchParam]);
                    } else {
                        $stmt = $conn->query("SELECT * FROM ucitel_zamestnanec ORDER BY prijmeni, jmeno");
                    }

                    if ($stmt->rowCount() === 0) {
                        echo "<tr><td colspan='5' style='text-align: center;'>Žádní zaměstnanci nenalezeni.</td></tr>";
                    } else {
                        while($row = $stmt->fetch()) {
                            echo "<td>" . htmlspecialchars($row['zamestnanecID']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['jmeno']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['prijmeni']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['kabinet']) . "</td>";
                            echo "<td class='action-buttons'>
                                    <form method='POST' style='display: inline;' onsubmit='return confirm(\"Opravdu smazat tohoto zaměstnance?\")'>
                                        <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCSRFToken()) . "'>
                                        <input type='hidden' name='zamestnanec_id' value='" . htmlspecialchars($row['zamestnanecID']) . "'>
                                        <button type='submit' name='smazat_zamestnance' class='btn-delete'>🗑️ Smazat</button>
                                    </form>
                                  </td>";
                            echo "</tr>";
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Chyba při načítání zaměstnanců: " . $e->getMessage());
                    echo "<tr><td colspan='5' style='text-align: center; color: red;'>Chyba při načítání dat.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal pro přidání zaměstnance -->
    <div id="addEmployeeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addEmployeeModal')">&times;</span>
            <h2>Přidat nového zaměstnance</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-group">
                    <label for="jmeno">Jméno:</label>
                    <input type="text" id="jmeno" name="jmeno" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="prijmeni">Příjmení:</label>
                    <input type="text" id="prijmeni" name="prijmeni" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="kabinet">Kabinet:</label>
                    <input type="text" id="kabinet" name="kabinet" maxlength="50" placeholder="např. Kabinet č. 101 nebo Knihovna" required>
                </div>
                <button type="submit" name="pridat_zamestnance" class="btn-primary">Přidat zaměstnance</button>
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
        const modal = document.getElementById('addEmployeeModal');
        if (event.target === modal) modal.style.display = "none";
    }

    if (window.location.search.includes('success=') || window.location.search.includes('deleted=')) {
        setTimeout(function() {
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 3000);
    }
</script>
</body>
</html>