<?php
    const LOGIN_PAGE_PATH = '../../crud/';
    require_once '../../crud/auth.php';

    require_once '../../config/database.php';
    require_once '../../models/Admin.php';
    require_once '../../models/Team.php';
    require_once '../../models/Event.php';

    // involved events
    const EVENTS = [
        [
            'slug'    => 'street-dancing',
            'percent' => 25
        ],
        [
            'slug'    => 'dance-exhibition',
            'percent' => 75
        ]
    ];

    // initialize titles
    $titles = ['1st Place', '2nd Place', '3rd Place'];

    // initialize admin
    $admin = new Admin();

    // initialize and tabulate events
    $event_deductions  = Event::findBySlug('deductions');
    $result_deductions = $admin->tabulate($event_deductions);
    $events  = [];
    $results = [];
    $competition_title = '';
    $judges     = [];
    $technicals = $event_deductions->getAllTechnicals();
    for($i=0; $i<sizeof(EVENTS); $i++) {
        $events[]  = Event::findBySlug(EVENTS[$i]['slug']);
        $results[] = $admin->tabulate($events[$i]);

        if($i == 0) {
            $competition_title = $events[$i]->getCategory()->getCompetition()->getTitle();
            $judges = $events[$i]->getAllJudges();
        }
    }

    // process result
    $result = [];
    $unique_net_totals = [];
    $unique_total_percentages = [];
    $unique_adjusted_ranks = [];
    foreach(Team::all() as $team) {
        $team_key = 'team_'.$team->getId();

        $t = [
            'info'   => $team->toArray(),
            'inputs' => [],
            'average'     => 0,
            'net_average' => 0,
            'deduction'   => $result_deductions['teams'][$team_key]['deductions']['average'],
            'rank' => [
                'total'     => 0,
                'net_total' => 0,
                'dense'     => 0,
                'initial'   => 0,
                'adjusted'  => 0,
                'final' => [
                    'dense'      => 0,
                    'fractional' => 0
                ]
            ],
            'title' => ''
        ];

        // get rank and average
        for($i=0; $i<sizeof(EVENTS); $i++) {
            $r = [
                'average'        => 0,
                'average_equiv'  => 0,
                'rank'           => 0,
                'rank_ave'       => 0,
                'rank_ave_equiv' => 0
            ];

            if(isset($results[$i]['teams'][$team_key])) {
                $r['average']        = $results[$i]['teams'][$team_key]['ratings']['average'];
                $r['average_equiv']  = $r['average'] * (EVENTS[$i]['percent'] / 100.0);
                $r['rank']           = $results[$i]['teams'][$team_key]['rank']['final']['fractional'];
                $r['rank_ave']       = 100 - $r['rank'];
                $r['rank_ave_equiv'] = $r['rank_ave'] * (EVENTS[$i]['percent'] / 100.0);
            }

            // append $r to $t['inputs']
            $t['inputs'][EVENTS[$i]['slug']] = $r;

            // accumulate totals
            $t['average'] += $r['average_equiv'];
            $t['rank']['total'] += $r['rank_ave_equiv'];
        }

        // compute totals
        $deduction = $result_deductions['teams'][$team_key]['deductions']['average'];
        $t['net_average'] = $t['average'] - $t['deduction'];
        $t['rank']['net_total'] = $t['rank']['total'] - $t['deduction'];

        // push $t['rank']['net_total'] to $unique_net_totals
        if(!in_array($t['rank']['net_total'], $unique_net_totals))
            $unique_net_totals[] = $t['rank']['net_total'];

        // append $t to $result
        $result[$team_key] = $t;
    }

    // sort $unique_net_totals
    rsort($unique_net_totals);

    // gather $rank_group (for getting fractional rank)
    $rank_group = [];
    foreach($result as $team_key => $team) {
        // get dense rank
        $dense_rank = 1 + array_search($team['rank']['net_total'], $unique_net_totals);
        $result[$team_key]['rank']['dense'] = $dense_rank;

        // push $team_key to $rank_group
        $key_rank = 'rank_' . $dense_rank;
        if(!isset($rank_group[$key_rank]))
            $rank_group[$key_rank] = [];
        $rank_group[$key_rank][] = $team_key;
    }

    // get initial fractional rank
    $ctr = 0;
    for($i = 0; $i < sizeof($unique_net_totals); $i++) {
        $key = 'rank_' . ($i + 1);
        $group = $rank_group[$key];
        $size = sizeof($group);
        $initial_rank = $ctr + ((($size * ($size + 1)) / 2) / $size);

        // write $fractional_rank to $group members
        for($j = 0; $j < $size; $j++) {
            $result[$group[$j]]['rank']['initial'] = $initial_rank;

            // compute adjusted average
            $adjusted_rank = $initial_rank - ($result[$group[$j]]['net_average'] * 0.01);
            $result[$group[$j]]['rank']['adjusted'] = $adjusted_rank;

            // push to $unique_adjusted_ranks
            if(!in_array($adjusted_rank, $unique_adjusted_ranks))
                $unique_adjusted_ranks[] = $adjusted_rank;
        }

        $ctr += $size;
    }

    // sort $unique_adjusted_ranks
    sort($unique_adjusted_ranks);

    // gather $rank_group (for getting fractional rank)
    $rank_group = [];
    foreach($result as $team_key => $team) {
        // get dense rank
        $dense_rank = 1 + array_search($team['rank']['adjusted'], $unique_adjusted_ranks);
        $result[$team_key]['rank']['final']['dense'] = $dense_rank;

        // push $key to $rank_group
        $key_rank = 'rank_' . $dense_rank;
        if(!isset($rank_group[$key_rank]))
            $rank_group[$key_rank] = [];
        $rank_group[$key_rank][] = $team_key;
    }

    // get final fractional rank
    $unique_final_fractional_ranks = [];
    $ctr = 0;
    for($i = 0; $i < sizeof($unique_adjusted_ranks); $i++) {
        $key = 'rank_' . ($i + 1);
        $group = $rank_group[$key];
        $size = sizeof($group);
        $final_fractional_rank = $ctr + ((($size * ($size + 1)) / 2) / $size);

        // push to $unique_final_fractional_ranks
        if(!in_array($final_fractional_rank, $unique_final_fractional_ranks))
            $unique_final_fractional_ranks[] = $final_fractional_rank;

        // write $fractional_rank to $group members
        for($j = 0; $j < $size; $j++) {
            $result[$group[$j]]['rank']['final']['fractional'] = $final_fractional_rank;
        }

        $ctr += $size;
    }

    // sort $unique_final_fractional_ranks
    sort($unique_final_fractional_ranks);

    // determine winners
    $winners = [];
    $i = 0;
    foreach($titles as $title) {
        // update title of $unique_final_fractional_ranks[$i]'th team
        foreach($result as $team_key => $team) {
            if($team['rank']['final']['fractional'] == $unique_final_fractional_ranks[$i]) {
                $result[$team_key]['title'] = $titles[$i];
                $winners[] = $team_key;
            }
        }

        $i += 1;
        if($i >= sizeof($unique_final_fractional_ranks))
            break;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="../../crud/dist/bootstrap-5.2.3/css/bootstrap.min.css">
    <style>
        th, td {
            vertical-align: middle;
        },
        .bt {
            border-top: 2px solid #aaa !important;
        }
        .br {
            border-right: 2px solid #aaa !important;
        }
        .bb, table.result tbody tr:last-child td {
            border-bottom: 2px solid #aaa !important;
        }
        .bl {
            border-left: 2px solid #aaa !important;
        }
    </style>
    <title>Overall Results | <?= $competition_title ?></title>
</head>
<body>
    <div class="p-1">
        <table class="table table-bordered result">
            <thead class="bt">
                <tr class="table-secondary">
                    <th colspan="3" rowspan="3" class="text-center bt br bl bb">
                        <h1 class="m-0">OVERALL RESULTS</h1>
                        <h5><?= $competition_title ?></h5>
                    </th>
                    <?php for($i=0; $i<sizeof($events); $i++) { ?>
                        <th colspan="3" class="text-center text-success bt br">
                            <h5 class="m-0"><?= $events[$i]->getTitle() ?></h5>
                        </th>
                    <?php } ?>
                    <th rowspan="3" class="text-center bl bt br bb">
                        TOTAL
                    </th>
                    <th rowspan="3" class="text-center text-danger bl bt br bb">
                        DEDUC-<br>TION
                    </th>
                    <th rowspan="3" class="text-center bl bt br bb" style="color: purple">
                        NET<br>TOTAL
                    </th>
                    <th rowspan="3" class="text-center bl bt br bb">
                        <span class="opacity-50">INITIAL<br>RANK</span>
                    </th>
                    <th rowspan="3" class="text-center bl bt br bb">
                        FINAL<br>RANK
                    </th>
                    <th rowspan="3" class="text-center bl bt br bb">
                        SLOT
                    </th>
                </tr>
                <tr class="table-secondary">
                    <?php for($i=0; $i<sizeof($events); $i++) { ?>
                        <th colspan="2" class="text-center bl"><span class="opacity-75">Average</span></th>
                        <th rowspan="2" class="bb br text-center"><h5 class="m-0"><?= EVENTS[$i]['percent'] ?>%</h5></th>
                    <?php } ?>
                </tr>
                <tr class="table-secondary">
                    <?php for($i=0; $i<sizeof($events); $i++) { ?>
                        <th colspan="2" class="bb text-center">Rank / Equiv.</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach($result as $team_key => $team) { ?>
                <tr<?= $team['title'] !== '' ? ' class="table-warning"' : '' ?>>
                    <!-- number -->
                    <td rowspan="2" class="pe-3 fw-bold bl bb" align="right">
                        <h3 class="m-0">
                            <?= $team['info']['number'] ?>
                        </h3>
                    </td>

                    <!-- avatar -->
                    <td rowspan="2" class="bb" style="width: 56px;">
                        <img
                            src="../../crud/uploads/<?= $team['info']['avatar'] ?>"
                            alt="<?= $team['info']['number'] ?>"
                            style="width: 56px; border-radius: 100%"
                        >
                    </td>

                    <!-- name -->
                    <td rowspan="2" class="br bb">
                        <h6 class="text-uppercase m-0"><?= $team['info']['name'] ?></h6>
                        <small class="m-0"><?= $team['info']['location'] ?></small>
                    </td>

                    <!-- averages -->
                    <?php for($i=0; $i<sizeof($events); $i++) { ?>
                        <td colspan="2" class="pe-3" align="right"><span class="opacity-75"><?= number_format($team['inputs'][EVENTS[$i]['slug']]['average'], 2) ?></span></td>
                        <td align="right" class="pe-3 br text-secondary fw-bold"><span class="opacity-75"><?= number_format($team['inputs'][EVENTS[$i]['slug']]['average_equiv'], 2) ?></span></td>
                    <?php } ?>

                    <!-- total average -->
                    <td class="br pe-3 text-secondary fw-bold" align="right"><?= number_format($team['average'], 2) ?></td>

                    <!-- deduction-->
                    <td rowspan="2" class="br bb pe-3 text-danger fw-bold" align="right"><?= number_format($team['deduction'], 2) ?></td>

                    <!-- net average -->
                    <td class="br pe-3" align="right"><h5 class="m-0 opacity-75"><?= number_format($team['net_average'], 2) ?></h5></td>

                    <!-- initial rank (spacer) -->
                    <td class="br"></td>

                    <!-- final rank (spacer) -->
                    <td class="br"></td>

                    <!-- slot -->
                    <td rowspan="2" class="bb br text-center" style="line-height: 1.1; vertical-align: bottom">
                        <h5 class="m-0"><?= $team['title'] ?></h5>
                    </td>
                </tr>

                <tr<?= $team['title'] !== '' ? ' class="table-warning"' : '' ?>>
                    <!-- ranks -->
                    <?php for($i=0; $i<sizeof($events); $i++) { ?>
                        <td align="right" class="bb pe-3 text-primary"><?= number_format($team['inputs'][EVENTS[$i]['slug']]['rank'], 2) ?></td>
                        <td align="right" class="bb pe-3 text-primary"><span class="opacity-75"><?= number_format($team['inputs'][EVENTS[$i]['slug']]['rank_ave'], 2) ?></span></td>
                        <td align="right" class="bb br pe-3 text-primary fw-bold"><span class="opacity-75"><?= number_format($team['inputs'][EVENTS[$i]['slug']]['rank_ave_equiv'], 2) ?></span></td>
                    <?php } ?>

                    <!-- total rank -->
                    <td class="br bb pe-3 text-primary fw-bold" align="right"><?= number_format($team['rank']['total'], 2) ?></td>

                    <!-- net total -->
                    <td class="br bb pe-3 fw-bold" align="right" style="color: purple"><h5 class="m-0"><?= number_format($team['rank']['net_total'], 2) ?></h5></td>

                    <!-- initial rank -->
                    <td class="br bb pe-3 fw-bold" align="right"><h5 class="m-0 opacity-50"><?= number_format($team['rank']['initial'], 2) ?></h5></td>

                    <!-- final rank -->
                    <td class="br bb pe-3 fw-bold" align="right"><h5 class="m-0"><?= number_format($team['rank']['final']['fractional'], 2) ?></h5></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <!-- Technicals and Judges -->
        <div class="container-fluid">
            <div class="row justify-content-center">
                <?php foreach($judges as $judge) { ?>
                    <div class="col-md-4">
                        <div class="mt-5 pt-3 text-center">
                            <h6 class="mb-0"><?= $judge->getName() ?></h6>
                        </div>
                        <div class="text-center">
                            <p class="mb-0">
                                Judge <?= $judge->getNumber() ?>
                                <?php if($judge->isChairmanOfEvent($events[0])) { ?>
                                    * (Chairman)
                                <?php } ?>
                            </p>
                        </div>
                    </div>
                <?php } ?>
                <?php foreach($technicals as $technical) { ?>
                    <div class="col-md-4">
                        <div class="mt-5 pt-3 text-center">
                            <h6 class="mb-0"><?= $technical->getName() ?></h6>
                        </div>
                        <div class="text-center">
                            <p class="mb-0">
                                Technical <?= $technical->getNumber() ?>
                            </p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Summary -->
        <div class="row justify-content-center pt-5 mt-5" style="page-break-before: always">
            <div class="col-12 col-sm-8 col-md-7 col-lg-6">
                <table class="table">
                    <thead>
                        <tr>
                            <th colspan="3" class="text-center pb-5">
                                <h1 class="m-0">OVERALL RESULTS</h1>
                                <h5><?= $competition_title ?></h5>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_winners = sizeof($winners);
                        for($i = ($total_winners - 1); $i >= 0; $i--) {
                            $team = $result[$winners[$i]];
                        ?>
                            <?php if($i < ($total_winners - 1)) { ?>
                                <tr>
                                    <td colspan="3" style="height: 100px;"></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td colspan="3" class="pa-3 text-center" style="border: 1px solid #ddd">
                                    <h3 class="m-0 fw-bold"><?= $team['title'] ?></h3>
                                </td>
                            </tr>

                            <tr>
                                <td
                                    class="text-center font-weight-bold pl-3 py-3 pr-6"
                                    style="border-left: 1px solid #ddd; border-bottom: 1px solid #ddd;"
                                >
                                    <h3 class="m-0"><?= $team['info']['number'] ?></h3>
                                </td>
                                <td style="width: 72px; padding-top: 8px !important; padding-bottom: 8px !important; border-bottom: 1px solid #ddd;">
                                    <img
                                        style="width: 100%; border-radius: 100%;"
                                        src="../../crud/uploads/<?= $team['info']['avatar'] ?>"
                                    />
                                </td>
                                <td
                                    class="pa-3"
                                    style="border-bottom: 1px solid #ddd; border-right: 1px solid #ddd;"
                                >
                                    <h5 class="m-0 text-uppercase fw-bold" style="line-height: 1.2"><?= $team['info']['name'] ?></h5>
                                    <p class="mt-1 text-body-1 mb-0" style="line-height: 1"><small><?= $team['info']['location'] ?></small></p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../../crud/dist/bootstrap-5.2.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>