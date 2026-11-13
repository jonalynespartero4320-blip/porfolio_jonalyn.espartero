<?php if(!empty($arrivalRecord['arrived_at'])):
    $staffQuery=$db->prepare('SELECT name FROM users WHERE id=?'); $staffQuery->execute([$arrivalRecord['arrived_by']]); $staffName=$staffQuery->fetchColumn(); $staffQuery->closeCursor();
?>
<span class="status confirmed">Arrived</span><small><?=e(date('M j, Y g:i A',strtotime($arrivalRecord['arrived_at'])))?> SGT</small><small>Recorded by <?=e($staffName?:'Staff')?></small>
<?php elseif(is_staff($me) && in_array($arrivalRecord['status'],['pending','confirmed'],true)): ?>
<span class="status pending">Awaiting arrival</span>
<?php if($arrivalRecord['date']<=date('Y-m-d')): ?>
<form class="arrival-action" method="post" action="<?=e(url('arrival'))?>"><?=csrf()?><input type="hidden" name="type" value="<?=e($arrivalType)?>"><input type="hidden" name="id" value="<?=e($arrivalRecord['id'])?>"><button class="button" type="submit">Confirm arrival</button></form>
<?php else: ?><div class="arrival-action"><button class="button" type="button" disabled>Confirm arrival</button></div><small>Available on the scheduled date</small><?php endif ?>
<?php else: ?><small>Not applicable</small>
<?php endif ?>
