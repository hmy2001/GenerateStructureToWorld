<?php

declare(strict_types=1);

namespace GenerateStructureToWorld;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use const pocketmine\RESOURCE_PATH;

class Main extends PluginBase{
	private $legacyIdMap, $colorData = [], $woodTypeData = [], $stoneTypeData = [], $cobbleStoneWallTypeData = [], $stoneSlabTypeData = [];

	public function onEnable(){
		$this->legacyIdMap = json_decode(file_get_contents(RESOURCE_PATH . "vanilla/block_id_map.json"), true);
		$this->generateBlockStateData();
	}

	private function generateBlockStateData(){
		$this->colorData = [
			"white",
			"orange",
			"magenta",
			"light_blue",
			"yellow",
			"lime",
			"pink",
			"gray",
			"silver",
			"cyan",
			"purple",
			"blue",
			"brown",
			"green",
			"red",
			"black",
		];
		$this->woodTypeData = [
			"oak",
			"spruce",
			"birch",
			"jungle",
			"acacia",
			"dark_oak"
		];
		$this->stoneTypeData = [
			"stone",
			"granite",
			"granite_smooth",
			"diorite",
			"diorite_smooth",
			"andesite",
			"andesite_smooth"
		];
		$this->cobbleStoneWallTypeData = [
			"cobblestone",
			"mossy_cobblestone",
			"granite",
			"diorite",
			"andesite",
			"sandstone",
			"brick",
			"stone_brick",
			"mossy_stone_brick",
			"end_brick",
			"prismarine",
			"red_sandstone",
			"red_nether_brick",
		];
		$this->stoneSlabTypeData = [
			"smooth_stone",
			"sand_stone",
			"wooden",
			"cobblestone",
			"brick",
			"stone_brick",
			"quartz",
			"nether_brick"
		];
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		$isPlayer = ($sender instanceof Player);
		switch($label){
			case "generate":
				if($isPlayer){
					if(isset($args[0])){
						$directory = $this->getDataFolder().$args[0].DIRECTORY_SEPARATOR;
						$directoryName = $args[0];
						if(is_dir($directory)){
							if(file_exists($directory."part1.mcstructure")){
								$this->generateStructure($directoryName, $directory);
							}else{
								$sender->sendMessage("Not structure directory: ".$directoryName);
								$sender->sendMessage("You must place part*.mcstructure. * is the number. one-based number.");
							}
						}else{
							$sender->sendMessage("Not exist directory: ".$directoryName);
						}
					}else{
						return false;
					}
				}else{
					$sender->sendMessage("Only Player");
				}
			break;
			case "create":
				if(isset($args[0])){
					$directory = $this->getDataFolder().$args[0].DIRECTORY_SEPARATOR;
					$directoryName = $args[0];
					if(is_dir($directory)){
						if(file_exists($directory."part1.mcstructure")){
							$this->createStructureJson($directoryName, $directory);
						}else{
							$sender->sendMessage("Not structure directory: ".$directoryName);
							$sender->sendMessage("You must place part*.mcstructure. * is the number. one-based number.");
						}
					}else{
						$sender->sendMessage("Not exist directory: ".$directoryName);
					}
				}else{
					return false;
				}
			break;
		}

		return true;
	}

	private function generateStructure(string $directoryName, string $directory){//TODO: startX, startY, startZ
		$this->getServer()->broadcastMessage("Generating structure: ".$directoryName);
		$startX = 0;
		$startY = 5;
		$startZ = 0;
		$structureData = $this->getStructureData($directory);
		foreach($structureData as $data){
			$x = $startX + $data[0];
			$y = $startY + $data[1];
			$z = $startZ + $data[2];
			$this->getServer()->getDefaultLevel()->setBlockIdAt($x, $y, $z, $data[3]);
			$this->getServer()->getDefaultLevel()->setBlockDataAt($x, $y, $z, $data[4]);
			//$this->getServer()->getDefaultLevel()->setBlockLightAt($x, $y, $z, 15);
			//$this->getServer()->getDefaultLevel()->setBlockSkyLightAt($x, $y, $z, 15);
			//TODO: update light
			//todo: add title
		}
		$this->getServer()->broadcastMessage("Generated structure: ".$directoryName);
	}

	private function createStructureJson(string $directoryName, string $directory){
		$this->getServer()->broadcastMessage("Creating structure json: ".$directoryName);
		$structureData = $this->getStructureData($directory);
		file_put_contents($this->getDataFolder().$directoryName.".json", json_encode($structureData, JSON_PRETTY_PRINT));
		$this->getServer()->broadcastMessage("Created structure json: ".$directoryName);
	}

	private function getStructureData(string $directory): array{
		$minX = $minY = $minZ = PHP_INT_MAX;
		$structureData = [];
		foreach(glob($directory."part*.mcstructure") as $fileName){
			$stream = new LittleEndianNBTStream();
			$nbt = $stream->read(file_get_contents($fileName));
			/** @var CompoundTag $nbt */
			$nbtData = LittleEndianNBTStream::toArray($nbt);
			$startX = $nbtData["structure_world_origin"][0];
			$startY = $nbtData["structure_world_origin"][1];
			$startZ = $nbtData["structure_world_origin"][2];

			if($minX > $startX){
				$minX = $startX;
			}
			if($minY > $startY){
				$minY = $startY;
			}
			if($minZ > $startZ){
				$minZ = $startZ;
			}

			$index = 0;
			for($x = 0; $x < $nbtData["size"][0]; $x++){
				for($y = 0; $y < $nbtData["size"][1]; $y++){
					for($z = 0; $z < $nbtData["size"][2]; $z++){
						$paletteIndex = $nbtData["structure"]["block_indices"][0][$index];
						$paletteData = $nbtData["structure"]["palette"]["default"]["block_palette"][$paletteIndex];
						//$blockEntityData = $nbtData["structure"]["palette"]["default"]["block_position_data"][$index]["block_entity_data"];
						/*if(isset($nbtData["structure"]["palette"]["default"]["block_position_data"][$index]["block_entity_data"])){
							var_dump($nbtData["structure"]["palette"]["default"]["block_position_data"][$index]["block_entity_data"]);
						}*/

						$blockData = $this->getBlockDataFromBlockPalette($paletteData);
						if($blockData[0] !== Block::AIR){
							$structureData[] = [$startX + $x, $startY + $y, $startZ + $z, $blockData[0], $blockData[1]];
						}
						$index++;
					}
				}
			}
		}

		$sortedStructureData = [];
		foreach($structureData as $data){
			$sortedStructureData[] = [$data[0] - $minX, $data[1] - $minY, $data[2] - $minZ, $data[3], $data[4]];
		}

		return $sortedStructureData;
	}

	private function getBlockDataFromBlockPalette(array $paletteData): array{
		$blockDamage = 0;
		$blockName = $paletteData["name"];
		if($blockName === "minecraft:structure_block"){
			$blockId = 0;//Air
		}else{
			if(isset($this->legacyIdMap[$blockName])){
				$blockId = $this->legacyIdMap[$blockName];
				if($blockId > 255){//255よりも大きい
					if($blockName === "minecraft:barrier"){
						$blockId = Block::INVISIBLE_BEDROCK;
					}else{
						echo $blockName."\n";
						$blockId = Block::RESERVED6;//update texture block
					}
				}else{
					switch($blockName){
						case "minecraft:stone":
							if(isset($paletteData["states"]["stone_type"])){
								if(($key = array_search($paletteData["states"]["stone_type"], $this->stoneTypeData, true)) !== false){
									$blockDamage = $key;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}
						break;
						case "minecraft:dirt":
							if(isset($paletteData["states"]["dirt_type"])){
								switch($paletteData["states"]["dirt_type"]){
									case "normal":
									break;
									case "coarse":
										$blockDamage = 1;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}
						break;
						case "minecraft:planks":
						case "minecraft:leaves":
						case "minecraft:fence":
							if(isset($paletteData["states"]["wood_type"])){
								if(($key = array_search($paletteData["states"]["wood_type"], $this->woodTypeData, true)) !== false){
									$blockDamage = $key;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}
						break;
						case "minecraft:water":
						case "minecraft:lava":
							/*if(isset($paletteData["states"]["liquid_depth"])){
								//TODO: implement it
							}*/
						break;

						case "minecraft:log":
							if(isset($paletteData["states"]["old_log_type"])){
								switch($paletteData["states"]["old_log_type"]){
									case "oak":
									break;
									case "spruce":
										$blockDamage = 1;
									break;
									case "birch":
										$blockDamage = 2;
									break;
									case "jungle":
										$blockDamage = 3;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}

							if(isset($paletteData["states"]["pillar_axis"])){
								switch($paletteData["states"]["pillar_axis"]){
									case "x":
										$blockDamage |= 0x04;
									break;
									case "y":
									break;
									case "z"://1000
										$blockDamage |= 0x08;
									break;
								}
							}
						break;
						case "minecraft:log2":
							if(isset($paletteData["states"]["new_log_type"])){
								switch($paletteData["states"]["new_log_type"]){
									case "acacia":
									break;
									case "dark_oak":
										$blockDamage = 1;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}

							if(isset($paletteData["states"]["pillar_axis"])){
								switch($paletteData["states"]["pillar_axis"]){
									case "x"://1
										$blockDamage |= 0x04;
									break;
									case "y"://0
									break;
									case "z"://2
										$blockDamage |= 0x08;
									break;
								}
							}
						break;
						case "minecraft:wool":
						case "minecraft:stained_hardened_clay":
						case "minecraft:concrete":
						case "minecraft:carpet":
						case "minecraft:stained_glass_pane":
							if(isset($paletteData["states"]["color"])){
								if(($key = array_search($paletteData["states"]["color"], $this->colorData, true)) !== false){
									$blockDamage = $key;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}
						break;

						case "minecraft:wooden_slab":
						case "minecraft:double_wooden_slab":
							if(isset($paletteData["states"]["wood_type"])){
								if(($key = array_search($paletteData["states"]["wood_type"], $this->woodTypeData, true)) !== false){
									$blockDamage = $key;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}

							if(isset($paletteData["states"]["top_slot_bit"])){
								if($paletteData["states"]["top_slot_bit"] === 1){
									$blockDamage |= 0x08;
								}
							}
						break;
						case "minecraft:stone_slab":
						case "minecraft:double_stone_slab":
							if(isset($paletteData["states"]["stone_slab_type"])){
								if(($key = array_search($paletteData["states"]["stone_slab_type"], $this->stoneSlabTypeData, true)) !== false){
									$blockDamage = $key;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}

							if(isset($paletteData["states"]["top_slot_bit"])){
								if($paletteData["states"]["top_slot_bit"] === 1){
									$blockDamage |= 0x08;
								}
							}
						break;
						case "minecraft:stone_slab2":
							if(isset($paletteData["states"]["stone_slab_type_2"])){
								if($paletteData["states"]["stone_slab_type_2"] === "mossy_cobblestone"){
									$blockDamage = 5;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}

							if(isset($paletteData["states"]["top_slot_bit"])){
								if($paletteData["states"]["top_slot_bit"] === 1){
									$blockDamage |= 0x08;
								}
							}
						break;
						case "minecraft:cobblestone_wall":
							if(isset($paletteData["states"]["wall_block_type"])){
								if(($key = array_search($paletteData["states"]["wall_block_type"], $this->cobbleStoneWallTypeData, true)) !== false){
									$blockDamage = $key;
								}else{
									var_dump([$blockName => $paletteData["states"]]);
								}
							}
						break;
						case "minecraft:smooth_stone"://TODO: convert
							//$blockId = 434;
							//$blockDamage = 0;
							var_dump([$blockName => $paletteData["states"]]);
						break;
						case "minecraft:mossy_cobblestone_stairs"://TODO: convert
							//$blockId = 434;
							var_dump([$blockName => $paletteData["states"]]);
						break;
						case "minecraft:oak_stairs":
						case "minecraft:spruce_stairs":
						case "minecraft:birch_stairs":
						case "minecraft:jungle_stairs":
						case "minecraft:acacia_stairs":
						case "minecraft:dark_oak_stairs":
						case "minecraft:stone_stairs":
						case "minecraft:stone_brick_stairs":
						case "minecraft:brick_stairs":
						case "minecraft:nether_brick_stairs":
						case "minecraft:sandstone_stairs":
						case "minecraft:red_sandstone_stairs":
						case "minecraft:quartz_stairs":
						case "minecraft:purpur_stairs":
							if(isset($paletteData["states"]["weirdo_direction"])){
								$blockDamage = $paletteData["states"]["weirdo_direction"];
							}

							if(isset($paletteData["states"]["upside_down_bit"])){
								if($paletteData["states"]["upside_down_bit"] === 1){
									$blockDamage |= 0x04;
								}
							}
						break;
						case "minecraft:quartz_block":
							if(isset($paletteData["states"]["stone_brick_type"])){
								switch($paletteData["states"]["stone_brick_type"]){
									case "default":
									break;
									case "chiseled":
										$blockDamage = 1;
									break;
									case "lines":
										if(isset($paletteData["states"]["pillar_axis"])){
											switch($paletteData["states"]["pillar_axis"]){
												case "x":
													$blockDamage = 4;
												break;
												case "y":
													$blockDamage = 2;
												break;
												case "z":
													$blockDamage = 3;
												break;
											}
										}
									break;
									case "smooth":
										//TODO: 分からん
										var_dump([$blockName => $paletteData["states"]]);
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}
						break;
						case "minecraft:prismarine":
							if(isset($paletteData["states"]["prismarine_block_type"])){
								switch($paletteData["states"]["prismarine_block_type"]){
									case "default":
									break;
									case "dark":
										$blockDamage = 1;
									break;
									case "bricks":
										$blockDamage = 2;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}
						break;
						case "minecraft:anvil":
						case "minecraft:chest":
						case "minecraft:bed":
							/*if(isset($paletteData["states"]["damage"])){//todo: this is anvil process
								switch($paletteData["states"]["damage"]){
									case "undamaged":
										$blockDamage = 0;
									break;
									case "slightly_damaged":
										$blockDamage = 4;
									break;
									case "very_damaged":
										$blockDamage = 8;
									break;
									case "broken":
										$blockDamage = 12;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}

							if(isset($paletteData["states"]["direction"])){
								//TODO: convert
							}*/
							$blockId = 0;//TODO: Tileをなんとかしないといけない
						break;
						case "minecraft:stonebrick":
							if(isset($paletteData["states"]["stone_brick_type"])){
								switch($paletteData["states"]["stone_brick_type"]){
									case "default":
									break;
									case "mossy":
										$blockDamage = 1;
									break;
									case "cracked":
										$blockDamage = 2;
									break;
									case "chiseled":
										$blockDamage = 3;
									break;
									case "smooth":
										$blockDamage = 4;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}
						break;
						case "minecraft:cauldron":
							if(isset($paletteData["states"]["fill_level"])){
								switch($paletteData["states"]["fill_level"]){
									case 0:
									break;
									case 1:
									case 2:
										$blockDamage = 1;
									break;
									case 3:
									case 4:
										$blockDamage = 2;
									break;
									case 5:
									case 6:
										$blockDamage = 3;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}

							if(isset($paletteData["states"]["cauldron_liquid"])){
								if($paletteData["states"]["cauldron_liquid"] !== "water"){
									$blockId = Block::RESERVED6;//update texture block
									$blockDamage = 0;
									var_dump([$blockName => $paletteData["states"]]);
								}
							}
						break;
						/*case "minecraft:yellow_glazed_terracotta":
						case "minecraft:piston":
						case "minecraft:end_rod":
						case "minecraft:pistonArmCollision":
							if(isset($paletteData["states"]["facing_direction"])){
								switch($paletteData["states"]["facing_direction"]){
									case 0:

									break;
									case 1:

									break;
									case 2:

									break;
									case 3:

									break;
									case 4:

									break;
									case 5:

									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}
						break;*/
						case "minecraft:torch":
							if(isset($paletteData["states"]["torch_facing_direction"])){
								switch($paletteData["states"]["torch_facing_direction"]){
									case "west":
										$blockDamage = 1;
									break;
									case "east":
										$blockDamage = 2;
									break;
									case "north":
										$blockDamage = 3;
									break;
									case "south":
										$blockDamage = 4;
									break;
									case "top":
										$blockDamage = 5;
									break;
									default:
										var_dump([$blockName => $paletteData["states"]]);
									break;
								}
							}
						break;
						case "minecraft:grass":
						case "minecraft:cobblestone":
						case "minecraft:bedrock":
						case "minecraft:gravel":
						case "minecraft:flower_pot":
						case "minecraft:info_update":
						case "minecraft:info_update2":
						case "minecraft:reserved6":
						break;
						default:
							if(!empty($paletteData["states"])){
								var_dump([$blockName => $paletteData["states"]]);
							}
						break;
					}
				}
			}else{
				$blockId = Block::RESERVED6;//update texture block
			}
		}

		return [$blockId, $blockDamage];
	}


}