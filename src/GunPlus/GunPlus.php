<?php

namespace GunPlus;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;

use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\math\Vector2;
use pocketmine\utils\Random;
use pocketmine\item\Item;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\String;

use pocketmine\network\protocol\ExplodePacket;
use pocketmine\network\protocol\Info as ProtocolInfo;
use pocketmine\network\protocol\PlayerActionPacket;

use pocketmine\event\server\DataPacketReceiveEvent;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityInventoryChangeEvent;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;

use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\EntityFlameParticle;

use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\PopSound;

class GunPlus extends PluginBase implements Listener {

	function onEnable ()
	{
		$plugin = "GunPlus";
		$this->getLogger()->info("§a".$plugin."を読み込みました §9By tukikage7127");
		$this->getLogger()->info("§c".$plugin."を二次配布するのは禁止です");

		Server::getInstance()->getPluginManager()->registerEvents($this,$this);
		
		$this->bullet = [351 => 1, 369 => 3, 352 => 32, 280 => 34, 268 => 15, 260 => 1, 322 => 1, 332 => 1, 284 => 45, 256 => 24, 290 => 6, 279 => 36, 292 => 18]; //弾数
		$this->reload = [346 => 2, 351 => 10, 369 => 7, 352 => 1, 280 => 3, 260 => 15, 322 => 15, 332 => 10, 284 => 5, 256 => 2, 290 => 5, 279 => 2, 292 => 1]; //リロード時間
		$this->vec = []; //地雷の座標入れる

		$this->weapon = [351 => "Mine", 369 => "M700", 352 => "G36c", 280 => "AK47", 288 => "ナイフ", 283 => "金剣", 268 => "投げナイフ", 332 => "火炎瓶", 284 => "P90", 256 => "M4A1", 290 => "SAA", 279 => "MP5", 292 => "G18"]; //武器名
		$this->heal = [260 => "Health Pack I", 322 => "Health Pack II"]; //回復系アイテム名
		$this->face = [351 => 15, 369 => 10, 352 => 3, 280 => 5, 283 => 4, 288 => 20, 268 => 10, 260 => 12, 322 => 20, 284 => 2, 256 => 4, 290 => 6, 279 => 3, 292 => 4, 332 => 5];//威力と回復量
	}

	function onJoin (PlayerJoinEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$this->tap[$name] = [351 =>0, 260 => 0, 322 => 0, 369 => 0, 352 => 0, 280 => 0, 268 => 0, 332 => 0, 284 => 0, 256 => 0, 290 => 0, 279 => 0, 292 => 0]; //弾数とかに関係ある
		$this->tick[$name] = [352 => 0, 280 => 0, 284 => 0, 256 => 0, 279 => 0, 292 => 0];//打つ間隔とかの
		$this->launch[$name] = [268 => false];//打ったかの判定
	}

	function onReceive (DataPacketReceiveEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$pk = $event->getPacket();
		if ($pk::NETWORK_ID == ProtocolInfo::PLAYER_ACTION_PACKET) {
			if ($pk->action == PlayerActionPacket::ACTION_ABORT_BREAK) {
				$id = $player->getInventory()->getItemInHand()->getId();
				switch ($id) {
					case 284:
						$this->tick[$name][$id]++;
						if ($this->tick[$name][$id] === 5) $this->tick[$name][$id] = 0;
						else return false;
						$this->tap[$name][$id]++;
						if ($this->tap[$name][$id] <= $this->bullet[$id]) {
							$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$N = -sin($player->pitch/180*M_PI)*3;
							$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$eid = mt_rand(100000, 10000000);
							$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
							$this->motion[$eid] = new Vector3($O,$N,$M);
							$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
							$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
							$this->move($player,$eid, $this->face[$id],"p90");
							Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),50);
							$bullet = $this->bullet[$id] - $this->tap[$name][$id];
							$player->sendPopup("残弾     ".$bullet."            P90            ".$bullet."     残弾");
							if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
						}else{
							$player->sendPopup("装填中     P90                              P90     装填中");
							$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						}
					break;

					case 352:
						$this->tick[$name][$id]++;
						if ($this->tick[$name][$id] === 25) $this->tick[$name][$id] = 0;
						else return false;
						$this->tap[$name][$id]++;
						if ($this->tap[$name][$id] <= $this->bullet[$id]) {
							$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$N = -sin($player->pitch/180*M_PI)*3;
							$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$eid = mt_rand(100000, 10000000);
							$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
							$this->motion[$eid] = new Vector3($O,$N,$M);
							$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
							$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
							$this->move($player,$eid, $this->face[$id],"g36c");
							Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),60);
							$bullet = $this->bullet[$id] - $this->tap[$name][$id];
							$player->sendPopup("残弾     ".$bullet."            G36c            ".$bullet."     残弾");
							if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
						}else{
							$player->sendPopup("装填中     G36c                              G36c     装填中");
							$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						}
					break;

					case 280:
						$this->tick[$name][$id]++;
						if ($this->tick[$name][$id] === 30) $this->tick[$name][$id] = 0;
						else return false;
						$this->tap[$name][$id]++;
						if ($this->tap[$name][$id] <= $this->bullet[$id]) {
							$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$N = -sin($player->pitch/180*M_PI)*3;
							$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$eid = mt_rand(100000, 10000000);
							$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
							$this->motion[$eid] = new Vector3($O,$N,$M);
							$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
							$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
							$this->move($player,$eid, $this->face[$id],"ak47");
							Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),60);
							$bullet = $this->bullet[$id] - $this->tap[$name][$id];
							$player->sendPopup("残弾     ".$bullet."            AK47            ".$bullet."     残弾");
							if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
						}else{
							$player->sendPopup("装填中     AK47                              AK47     装填中");
							$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						}
					break;

					case 256:
						$this->tick[$name][$id]++;
						if ($this->tick[$name][$id] === 35) $this->tick[$name][$id] = 0;
						else return false;
						$this->tap[$name][$id]++;
						if ($this->tap[$name][$id] <= $this->bullet[$id]) {
							$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$N = -sin($player->pitch/180*M_PI)*3;
							$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$eid = mt_rand(100000, 10000000);
							$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
							$this->motion[$eid] = new Vector3($O,$N,$M);
							$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
							$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
							$this->move($player,$eid, $this->face[$id],"m4a1");
							Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),70);
							$bullet = $this->bullet[$id] - $this->tap[$name][$id];
							$player->sendPopup("残弾     ".$bullet."            M4A1            ".$bullet."     残弾");
							if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
						}else{
							$player->sendPopup("装填中     M4A1                              M4A1     装填中");
							$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						}
					break;

					case 279:
						$this->tick[$name][$id]++;
						if ($this->tick[$name][$id] === 5) $this->tick[$name][$id] = 0;
						else return false;
						$this->tap[$name][$id]++;
						if ($this->tap[$name][$id] <= $this->bullet[$id]) {
							$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$N = -sin($player->pitch/180*M_PI)*3;
							$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$eid = mt_rand(100000, 10000000);
							$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
							$this->motion[$eid] = new Vector3($O,$N,$M);
							$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
							$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
							$this->move($player,$eid, $this->face[$id],"mp5");
							Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),50);
							$bullet = $this->bullet[$id] - $this->tap[$name][$id];
							$player->sendPopup("残弾     ".$bullet."            MP5            ".$bullet."     残弾");
							if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
						}else{
							$player->sendPopup("装填中     MP5                              MP5     装填中");
							$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						}
					break;

					case 292:
						$this->tick[$name][$id]++;
						if ($this->tick[$name][$id] === 40) $this->tick[$name][$id] = 0;
						else return false;
						$this->tap[$name][$id]++;
						if ($this->tap[$name][$id] <= $this->bullet[$id]) {
							$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$N = -sin($player->pitch/180*M_PI)*3;
							$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
							$eid = mt_rand(100000, 10000000);
							$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
							$this->motion[$eid] = new Vector3($O,$N,$M);
							$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
							$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
							$this->move($player,$eid,$this->face[$id],"g18");
							Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),40);
							$bullet = $this->bullet[$id] - $this->tap[$name][$id];
							$player->sendPopup("残弾     ".$bullet."            G18            ".$bullet."     残弾");
							if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
						}else{
							$player->sendPopup("装填中     G18                              G18     装填中");
							$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						}
					break;
				}
			}
		}
	}

	function onTap (PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$name = $player->getName();
		$item = $player->getInventory()->getItemInHand();
		$id = $item->getId();
		$block = $event->getBlock();
		if ($item->isShovel() or $item->isHoe()) $event->setCancelled();
		switch ($id) {
			case 351:
				$meta = $item->getDamage();
				if ($meta === 1) {
					$this->tap[$name][$id]++;
					if ($this->tap[$name][$id] <= $this->bullet[$id]) {
						$x = $block->x;
						$y = $block->y;
						$z = $block->z;
						$this->vec[$x.":".$y.":".$z] = $player->getName();
						$player->getLevel()->addSound(new AnvilFallSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						$bullet = $this->bullet[$id] - $this->tap[$name][$id];
						$player->sendPopup("個数     ".$bullet."               Mine               ".$bullet."     個数");
						if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
					}else{
						$player->sendPopup("装填中     Mine                              Mine     装填中");
						$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
					}
				}
			break;

			case 260:
				$this->tap[$name][$id]++;
				if ($this->tap[$name][$id] <= $this->bullet[$id]) {
					$player->setHealth($player->getHealth() + $this->face[$id]);
					$player->getLevel()->addSound(new PopSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
					$bullet = $this->bullet[$id] - $this->tap[$name][$id];
					$player->sendPopup("個数     ".$bullet."          Health Pack I         ".$bullet."     個数");
					if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
				}else{
					$player->sendPopup("準備中     Health Pack I       Health Pack I     準備中");
					$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
				}
			break;

			case 322:
				$this->tap[$name][$id]++;
				if ($this->tap[$name][$id] <= $this->bullet[$id]) {
					$player->setHealth($player->getHealth() + $this->face[$id]);
					$player->getLevel()->addSound(new PopSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
					$bullet = $this->bullet[$id] - $this->tap[$name][$id];
					$player->sendPopup("個数     ".$bullet."          Health Pack II         ".$bullet."     個数");
					if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
				}else{
					$player->sendPopup("準備中     Health Pack II     Health Pack II     準備中");
					$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
				}
			break;

			case 369:
				if ($block->getId() === 0) {
					$this->tap[$name][$id]++;
					if ($this->tap[$name][$id] <= $this->bullet[$id]) { 
						$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
						$N = -sin($player->pitch/180*M_PI)*3;
						$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
						$eid = mt_rand(100000, 10000000);
						$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
						$this->motion[$eid] = new Vector3($O,$N,$M);
						$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
						$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
						$this->move($player,$eid,$this->face[$id],"m700");
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),100);
						$bullet = $this->bullet[$id] - $this->tap[$name][$id];
						$player->sendPopup("残弾     ".$bullet."            M700            ".$bullet."     残弾");
						if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
					}else{
						$player->sendPopup("装填中     M700                              M700     装填中");
						$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
					}
				}
			break;

			case 268:
				if ($block->getId() === 0) {
					$this->tap[$name][$id]++;
					if ($this->tap[$name][$id] <= $this->bullet[$id]) {
						$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
						$N = -sin($player->pitch/180*M_PI)*3;
						$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
						$eid = mt_rand(100000, 10000000);
						$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
						$this->motion[$eid] = new Vector3($O,$N,$M);
						$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
						$player->getLevel()->addSound(new AnvilFallSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
						$this->move($player,$eid,$this->face[$id],"kt",true);
						$this->launch[$name][$id] = true;
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),20);
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"launch"],[$name,$id]),20);
						$bullet = $this->bullet[$id] - $this->tap[$name][$id];
						$player->sendPopup("個数     ".$bullet."           投げナイフ           ".$bullet."     個数");
						$item->setDamage($item->getDamage() + 4);
						$player->getInventory()->setItemInHand($item);
						if ($bullet <= 0 or $item->getDamage() >= 60) {
							$player->getInventory()->setItemInHand(Item::get(0,0,0));
							$this->tap[$name][$id] = 0;
						}
					}
				}
			break;

			case 332:
				if ($block->getId() === 0) {
					$this->tap[$name][$id]++;
					if ($this->tap[$name][$id] <= $this->bullet[$id]) {
						$player->getLevel()->addSound(new AnvilFallSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
						$bullet = $this->bullet[$id] - $this->tap[$name][$id];
						$player->sendPopup("個数     ".$bullet."               火炎瓶               ".$bullet."     個数");
						if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id,true]), $this->reload[$id]*20);
					}else{
						$player->sendPopup("装填中     火炎瓶                              火炎瓶     装填中");
						$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
					}
				}
			break;

			case 290:
				if ($block->getId() === 0) {
					$this->tap[$name][$id]++;
					if ($this->tap[$name][$id] <= $this->bullet[$id]) { 
						$O = -sin($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
						$N = -sin($player->pitch/180*M_PI)*3;
						$M = cos($player->yaw/180*M_PI)*cos($player->pitch/180*M_PI)*3;
						$eid = mt_rand(100000, 10000000);
						$this->pos[$eid] = new Vector3($player->x+$O/2, $player->y+$player->getEyeHeight()+$N/2-0.02, $player->z+$M/2);
						$this->motion[$eid] = new Vector3($O,$N,$M);
						$this->Projectile[$eid] = Server::getInstance()->getOnlinePlayers();
						$player->getLevel()->addSound(new BlazeShootSound(new Vector3($player->x, $player->y, $player->z)), [$player]);
						$this->move($player,$eid,$this->face[$id],"saa");
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"close"],[$eid]),20);
						$bullet = $this->bullet[$id] - $this->tap[$name][$id];
						$player->sendPopup("残弾     ".$bullet."            SAA            ".$bullet."     残弾");
						if ($bullet <= 0) Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"reload"],[$player,$id]), $this->reload[$id]*20);
					}else{
						$player->sendPopup("装填中     SAA                              SAA     装填中");
						$player->getLevel()->addSound(new ClickSound(new Vector3($player->x,$player->y,$player->z)), [$player]);
					}
				}
			break;
		}
	}

	function onHit (EntityDespawnEvent $event)
	{
		$entity = $event->getEntity();
		if ($entity::NETWORK_ID == 81) {
			$shooter = $entity->shootingEntity;
			$v3 = new Vector3($entity->x,$entity->y,$entity->z);
			$level = $shooter->getLevel();
			$this->ExplodeDamage($v3,$level,$shooter,$this->face[332],true);
            $p = new EntityFlameParticle($v3);
            for($yaw = 0, $y = $v3->y; $y < $v3->y + 4; $yaw += (M_PI * 2) / 20, $y += 1 / 20){
                $x = -sin($yaw) + $v3->x;
                $z = cos($yaw) + $v3->z;
                $p->setComponents($x, $y, $z);
                $level->addParticle($p);
			}
		}
	}

	function onItem (EntityInventoryChangeEvent $event)
	{
		if (!$event->getEntity() instanceof Player) return false;
		$item = $event->getNewItem();
		if (isset($this->weapon[$item->getId()])) {
			$reload = isset($this->reload[$item->getId()]) ? "\n§7リロード時間: ".$this->reload[$item->getId()]."秒" : "";
			$tag = "§b".$this->weapon[$item->getId()]."\n§7攻撃力: ".$this->face[$item->getId()].$reload;
			$item->setCustomName($tag);
			$event->setNewItem($item);
		}elseif (isset($this->heal[$item->getId()])) {
			$reload = isset($this->reload[$item->getId()]) ? "\n§7準備時間: ".$this->reload[$item->getId()]."秒" : "";
			$tag = "§b".$this->heal[$item->getId()]."\n§7回復力: ".$this->face[$item->getId()].$reload;
			$item->setCustomName($tag);
			$event->setNewItem($item);
		}
	}

	function onDamage (EntityDamageEvent $event)
	{
		if ($event instanceof EntityDamageByEntityEvent) {
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			$event->setKnockBack(0);
			if ($entity instanceof Player and $damager instanceof Player) {
				$item = $damager->getInventory()->getItemInHand();
				if ($item->getId() === 268) {
					if ($this->launch[$damager->getName()][268]) {
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"isBreak"],[$damager,$item]),1);
					}else{
						$event->setDamage($this->getFinalDamage($entity,4));
					}
				}
			}
		}
	}

	function onMove (PlayerMoveEvent $event)
	{
		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$x = $player->x;
		$y = $player->y - 1;
		$z = $player->z;
		$x = round($x);
		$y = round($y);
		$z = round($z);
		$key = $x.":".$y.":".$z;
		if (array_key_exists($key, $this->vec)) {
			if ($this->vec[$key] != $name) {
				$damager = Server::getInstance()->getPlayer($this->vec[$key]);
				$this->ExplodeDamage(new Vector3($x,$y,$z),$player->getLevel(),$damager,$this->face[$id]);
			}
		}
	}

	function isBreak ($player, $item)
	{
		if ($player->getInventory()->getItemInHand()->getId() === 0) $this->tap[strtolower($player->getName())][$item->getId()] = 0;
	}

	function ExplodeDamage (Vector3 $v3, $level, $damager, $damage, $value = false)
	{//爆破によるダメージ
		$x = $v3->x;
		$y = $v3->y;
		$z = $v3->z;
		$players = Server::getInstance()->getOnlinePlayers();
		$this->explode($v3, $level, 3);
		foreach ($players as $player) {
			$px = $player->x;
			$py = $player->y;
			$pz = $player->z;
			$k = sqrt(pow($x-$px,2)+pow($y-$py,2)+pow($z-$pz,2));
			if ($k < 5) {
				$d = $this->getFinalDamage($player, $damage);
				$ev = ($damager instanceof Player) ? (new EntityDamageByEntityEvent($damager, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $d)) : (new EntityDamageEvent($player, EntityDamageEvent::CAUSE_CUSTOM, $d));
				$player->attack($d, $ev);
				if ($value) $player->setOnFire(5);
			}
		}
		unset($this->vec[$x.":".$y.":".$z]);
	}

	function explode (Vector3 $v3, $level, $size)
	{//爆発
		$pk = new ExplodePacket();
		$pk->x = $v3->x;
		$pk->y = $v3->y;
		$pk->z = $v3->z;
		$pk->radius = $size;
		$pk->records = [];
		$level->addChunkPacket($v3->x >> 4, $v3->z >> 4, $pk);
	}

	function move ($player,$eid,$damage,$type,$value = false)
	{
		//$typeは将来使うかもだから一応keep
		if (!isset($this->pos[$eid])) return false;
		$H = $this->pos[$eid];
		$F = $this->motion[$eid];
		if ($value) $F->y-=0.05;
		$this->pos[$eid] = new Vector3($H->x+$F->x, $H->y+$F->y, $H->z+$F->z);
		$player->level->addParticle(new DustParticle($H,0,0,0));
		for ($K = 1; $K < 4; $K++) {
			$H = new Vector3($H->x+$F->x/$K, $H->y+$F->y/$K, $H->z+$F->z/$K);
			if ($player->level->getBlock($H)->isSolid()) {
				$this->close($eid);
				break;
				return false;
			}
			foreach (Server::getInstance()->getOnlinePlayers() as $p) {
				$x = $p->x;
				$y = $p->y;
				$z = $p->z;
				$c = new Vector2($x, $z);
				if ((new Vector2($H->x, $H->z))->distance($c) <= 1.2 && $H->y-$p->y <= 2.6 && $H->y-$p->y > 0) {
					if($p->getName() != $player->getName()) {
						$d = $this->getFinalDamage($p, $damage);
						$ev = new EntityDamageByEntityEvent($player, $p, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $d);
						$p->attack($d, $ev);
					}
					return $this->close($eid);
				}
			}
		}
		Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this,"move"],[$player,$eid,$damage]),1);
	}

	function close($eid)
	{
		if (!isset($this->Projectile[$eid])) {
			if (isset($this->pos[$eid])) unset($this->pos[$eid]);
			return true;
		}
		unset ($this->Projectile[$eid]);
		if(isset($this->pos[$eid])) unset($this->pos[$eid]);
	}

	function launch ($name,$id)
	{
		if (isset($this->launch[$name][$id])) $this->launch[$name][$id] = false;
	}

	function reload ($player, $id, $value = false)
	{
		$name = $player->getName();
		$this->tap[$name][$id] = 0;
		$item = [351 => "Mine", 369 => "M700", 352 => "G36c", 280 => "AK47", 260 => "Health Pack I", 322 => "Health Pack II", 332 => "火炎瓶", 284 => "P90", 256 => "M4A1", 290 => "SAA", 292 => "G18", 279 => "MP5"];
		$player->sendPopup("               装填完了       ".$item[$id]."       装填完了               ");
		if ($value) {
			if ($player instanceof Player) {
				$item = Item::get($id,0,1);
				if ($player->getInventory() != null) {
					if (!$player->getInventory()->contains($item)) $player->getInventory()->addItem($item);
				}
			}
		}
	}

	function getFinalDamage($A,$D)
	{
		$S = [Item::LEATHER_CAP=>1,Item::LEATHER_TUNIC=>3,Item::LEATHER_PANTS=>2,Item::LEATHER_BOOTS=>1,Item::CHAIN_HELMET=>1,Item::CHAIN_CHESTPLATE=>5,Item::CHAIN_LEGGINGS=>4,Item::CHAIN_BOOTS=>1,Item::GOLD_HELMET=>1,Item::GOLD_CHESTPLATE=>5,Item::GOLD_LEGGINGS=>3,Item::GOLD_BOOTS=>1,Item::IRON_HELMET=>2,Item::IRON_CHESTPLATE=>6,Item::IRON_LEGGINGS=>5,Item::IRON_BOOTS=>2,Item::DIAMOND_HELMET=>3,Item::DIAMOND_CHESTPLATE=>8,Item::DIAMOND_LEGGINGS=>6,Item::DIAMOND_BOOTS=>3];
		$T = 0;
		foreach($A->getInventory()->getArmorContents() as $g => $K){
			if(isset($S[$K->getId()])){
				$T+=$S[$K->getId()];
			}
		}
		$D+=-floor($D*$T*0.04);
		if ($D<1) $D=1;
		return$D;
	}
}

class Callback extends Task {

	function __construct(callable $callable, array $args = [])
    {
        $this->callable = $callable;
        $this->args = $args;
        $this->args[] = $this;
    }

    function getCallable()
    {
        return $this->callable;
    }
        
    function onRun ($tick)
    {
        call_user_func_array($this->callable, $this->args);
    }
}