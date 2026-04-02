<?php defined( 'ABSPATH' ) || die( 'Cheating uh?' ); ?>
<?php if ( ! isset( $view ) ) {
	return;
} ?>
<?php
if ( HMWP_Classes_Tools::getOption( 'hmwp_threats_log' ) ) {
    HMWP_Classes_ObjController::getClass( 'HMWP_Classes_DisplayController' )->loadMedia( 'chart.umd' );

    /** @var HMWP_Models_ThreatsLog $threatsLog */
    $threatsLog = HMWP_Classes_ObjController::getClass( 'HMWP_Models_ThreatsLog' );
    $data = $threatsLog->getThreatStatsByDay( 7 );

    if ( $data && isset( $data['date'] ) && count( $data['date'] ) >= 2 ){
        ?>
        <div class="p-3">
            <canvas id="hmwpThreatsChart" height="200" style="max-width:100%; max-height: 280px;"></canvas>
        </div>
        <script>
            (function () {
                if (!window.Chart) return;

                var el = document.getElementById("hmwpThreatsChart");
                if (!el) return;

                var s = <?php echo json_encode($data) ?> || {};
                var date    = s.date || [];
                var blocked = s.blocked || [];
                var threats = s.threats || [];

                // Passed = total threats - prevented threats
                var passed = threats.map(function(total, i) {
                    var prevented = parseInt(blocked[i], 10) || 0;
                    total = parseInt(total, 10) || 0;
                    return Math.max(0, total - prevented);
                });

                var urlPassed  = <?php echo json_encode( HMWP_Classes_Tools::getSettingsUrl( 'hmwp_log&thr_blocked=0&tab=threats', true ) ); ?>;
                var urlBlocked = <?php echo json_encode( HMWP_Classes_Tools::getSettingsUrl( 'hmwp_log&thr_blocked=1&tab=threats', true ) ); ?>;

                var colorPassed  = "#FFA500";
                var colorBlocked = "#3F72AF";

                new Chart(el, {
                    type: "bar",
                    data: {
                        labels: date,
                        datasets: [
                            {
                                label: "<?php echo esc_attr__( 'Threats Prevented', 'hide-my-wp' ) ?>",
                                data: blocked,
                                backgroundColor: colorBlocked,
                                borderColor: colorBlocked,
                                borderWidth: 0,
                                borderRadius: 2,
                                maxBarThickness: 15,
                                stack: 'threats'
                            },
                            {
                                label: "<?php echo esc_attr__( 'Threats Passed', 'hide-my-wp' ) ?>",
                                data: passed,
                                backgroundColor: colorPassed,
                                borderColor: colorPassed,
                                borderWidth: 0,
                                borderRadius: 2,
                                maxBarThickness: 15,
                                stack: 'threats'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    footer: function(items) {
                                        if (!items.length) return '';
                                        var index = items[0].dataIndex;
                                        return 'Total: ' + (parseInt(threats[index], 10) || 0);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true
                            },
                            y: {
                                beginAtZero: true,
                                stacked: true
                            }
                        },

                        onHover: function (evt, elements) {
                            if (evt.native) {
                                evt.native.target.style.cursor = elements.length ? "pointer" : "default";
                            }
                        },

                        onClick: function (evt, elements) {
                            if (!elements || !elements.length) return;

                            var e = elements[0];
                            var datasetIndex = e.datasetIndex; // 0 = blocked, 1 = passed
                            var index        = e.index;

                            var base = (datasetIndex === 0) ? urlBlocked : urlPassed;

                            var day = date[index] || "";
                            if (day) {
                                base += (base.indexOf("?") !== -1 ? "&" : "?") + "thr_day=" + encodeURIComponent(day);
                            }

                            window.location.href = base;
                        }
                    }
                });
            })();
        </script>
    <?php }
}?>