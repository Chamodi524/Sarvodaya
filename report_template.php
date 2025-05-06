<?php
// reports/payments_report_template.php
?>
<div class="report-container">
    <div class="report-header">
        <h3>Payments Report</h3>
        <p>Period: <?php echo date('Y-m-d', strtotime($start_date)); ?> to <?php echo date('Y-m-d', strtotime($end_date)); ?></p>
    </div>
    
    <div class="summary-section mb-4">
        <h4>Summary by Payment Type</h4>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Payment Type</th>
                    <th class="text-center">Count</th>
                    <th class="text-end">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['payment_type']); ?></td>
                    <td class="text-center"><?php echo $item['count']; ?></td>
                    <td class="text-end"><?php echo number_format($item['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <th>Total</th>
                    <th class="text-center"><?php 
                        $total_count = array_sum(array_column($summary, 'count')); 
                        echo $total_count;
                    ?></th>
                    <th class="text-end"><?php echo number_format($data['total'], 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="details-section">
        <h4>Payment Details</h4>
        <table class="table table-striped table-bordered">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data['payments']) > 0): ?>
                    <?php foreach ($data['payments'] as $payment): ?>
                    <tr>
                        <td><?php echo $payment['id']; ?></td>
                        <td><?php echo htmlspecialchars($payment['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                        <td class="text-end"><?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No payments found for this period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <th colspan="3">Total</th>
                    <th class="text-end"><?php echo number_format($data['total'], 2); ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php
// reports/receipts_report_template.php
?>
<div class="report-container">
    <div class="report-header">
        <h3>Receipts Report</h3>
        <p>Period: <?php echo date('Y-m-d', strtotime($start_date)); ?> to <?php echo date('Y-m-d', strtotime($end_date)); ?></p>
    </div>
    
    <div class="summary-section mb-4">
        <h4>Summary by Receipt Type</h4>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Receipt Type</th>
                    <th class="text-center">Count</th>
                    <th class="text-end">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['receipt_type']); ?></td>
                    <td class="text-center"><?php echo $item['count']; ?></td>
                    <td class="text-end"><?php echo number_format($item['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <th>Total</th>
                    <th class="text-center"><?php 
                        $total_count = array_sum(array_column($summary, 'count')); 
                        echo $total_count;
                    ?></th>
                    <th class="text-end"><?php echo number_format($data['total'], 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="details-section">
        <h4>Receipt Details</h4>
        <table class="table table-striped table-bordered">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data['receipts']) > 0): ?>
                    <?php foreach ($data['receipts'] as $receipt): ?>
                    <tr>
                        <td><?php echo $receipt['id']; ?></td>
                        <td><?php echo htmlspecialchars($receipt['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($receipt['receipt_type']); ?></td>
                        <td class="text-end"><?php echo number_format($receipt['amount'], 2); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($receipt['receipt_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No receipts found for this period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <th colspan="3">Total</th>
                    <th class="text-end"><?php echo number_format($data['total'], 2); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>