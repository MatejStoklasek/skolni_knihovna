
<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once 'config.php';

$conn = getDBConnection();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtuální Knihovna</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <h1>📚 Virtuální Knihovna</h1>
        <nav>
            <a href="main.php" class="active">Domů</a>
            <a href="knihy.php">Knihy</a>
            <a href="pujceni.php">Půjčení</a>
            <a href="zakaznici.php">Zákazníci</a>
            <a href="zamestnanci.php">Zaměstnanci</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h2>Vítejte v naší virtuální knihovně</h2>
            <p>Systém pro správu školní knihovny</p>
        </section>

        <div class="dashboard">
            <?php
            try {
            // Statistiky
            $total_knihy = $conn->query("SELECT COUNT(*) as count FROM kniha")->fetch()['count'];
            $total_pujceni = $conn->query("SELECT COUNT(*) as count FROM pujceni")->fetch()['count'];
            $total_zakaznici = $conn->query("SELECT COUNT(*) as count FROM zak_zakaznici")->fetch()['count'];
            $dostupne_knihy = $conn->query("SELECT SUM(ks) as total FROM kniha")->fetch()['total'] ?? 0;
            ?>

            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($total_knihy); ?></h3>
                    <p>Knih v systému</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($dostupne_knihy); ?></h3>
                    <p>Kusů celkem</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($total_pujceni); ?></h3>
                    <p>Aktivních půjčení</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($total_zakaznici); ?></h3>
                    <p>Registrovaných žáků</p>
                </div>
            </div>

            <section class="recent-loans">
                <h2>Poslední půjčení</h2>
                <table>
                    <thead>
                    <tr>
                        <th>Kniha</th>
                        <th>Žák</th>
                        <th>Třída</th>
                        <th>Vrátit do</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT k.nazev, 
                                   CONCAT(z.jmeno, ' ', z.prijmeni) as zak_cele_jmeno,
                                   z.trida, 
                                   p.delka_cas
                            FROM pujceni p
                            JOIN kniha k ON p.knihaID = k.knihaID
                            JOIN zak_zakaznici z ON p.zakID = z.zakID
                            ORDER BY p.datum_pujceni DESC
                            LIMIT 10";

                    $result = $conn->query($sql);

                    if ($result->rowCount() === 0) {
                        echo "<tr><td colspan='4' style='text-align: center;'>Žádná půjčení.</td></tr>";
                    } else {
                        while($row = $result->fetch()) {
                            $vraceni_date = new DateTime($row['delka_cas']);
                            $today = new DateTime();
                            $is_overdue = $vraceni_date < $today;

                            echo "<td>" . htmlspecialchars($row['nazev']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['zak_cele_jmeno']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['trida']) . "</td>";
                            echo "<td>" . date('d.m.Y', strtotime($row['delka_cas']));
                            if ($is_overdue) {
                                echo " <span class='badge-overdue'>⚠️ Po termínu</span>";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                    }

                    } catch (PDOException $e) {
                        error_log("Chyba při načítání statistik: " . $e->getMessage());
                        echo "<tr><td colspan='4' style='text-align: center; color: red;'>Chyba při načítání dat.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Virtuální Knihovna | Školní knihovní systém</p>
    </footer>
</div>
</body>
</html>