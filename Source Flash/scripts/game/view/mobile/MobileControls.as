package game.view.mobile
{
   import ddt.events.LivingEvent;
   import flash.display.Bitmap;
   import flash.display.Sprite;
   import flash.display.Stage;
   import flash.events.Event;
   import flash.events.MouseEvent;
   import flash.events.TouchEvent;
   import flash.external.ExternalInterface;
   import flash.system.Capabilities;
   import flash.ui.Multitouch;
   import flash.ui.MultitouchInputMode;
   import flash.utils.getTimer;
   import game.model.LocalPlayer;

   /**
    * Interface de controles de batalha para Flash/AIR.
    * O teclado do PC e as regras originais permanecem sob ArrowView/EnergyView.
    * A classe oferece somente o estado de entrada, inclusive um cancelamento
    * distinguivel da liberacao normal de FORCA.
    */
   public class MobileControls extends Sprite
   {
      [Embed(source="assets/angulo_mais.png", mimeType="image/png")]
      private static const ImgUp:Class;
      [Embed(source="assets/angulo_menos.png", mimeType="image/png")]
      private static const ImgDown:Class;
      [Embed(source="assets/forca.png", mimeType="image/png")]
      private static const ImgForce:Class;

      // PC: botões ocultos por padrão, teclado W/S, setas e SPACE mantidos.
      // Ative para testar os controles gráficos com mouse no desktop.
      public static const DESKTOP_BUTTONS:Boolean = false;
      // Use true se o emulador mobile nao expuser o sistema operacional/toque.
      public static const ALWAYS_SHOW_BUTTONS:Boolean = false;

      public static var angleUp:Boolean = false;
      public static var angleDown:Boolean = false;
      public static var forceDown:Boolean = false;
      /** Incrementado somente quando a carga foi INTERROMPIDA sem disparar. */
      public static var forceCancelSerial:uint = 0;

      private var _player:LocalPlayer;
      private var _up:Sprite;
      private var _down:Sprite;
      private var _force:Sprite;
      private var _mouseAction:String = "";
      private var _touches:Object = {};
      private var _ignoreMouseUntil:int = 0;
      private var _attachedStage:Stage;
      private var _disposed:Boolean = false;
      private var _oldTouchMode:String = "";
      private var _didSetTouchMode:Boolean = false;

      public function MobileControls(player:LocalPlayer)
      {
         super();
         _player = player;
         mouseEnabled = false;
         _up = makeButton(new ImgUp() as Bitmap, "up");
         _down = makeButton(new ImgDown() as Bitmap, "down");
         _force = makeButton(new ImgForce() as Bitmap, "force");
         addChild(_up);
         addChild(_down);
         addChild(_force);
         if(_player != null)
         {
            _player.addEventListener(LivingEvent.ATTACKING_CHANGED, onStatus);
            _player.addEventListener(LivingEvent.DIE, onStatus);
         }
         addEventListener(Event.ADDED_TO_STAGE, onAdded);
         addEventListener(Event.REMOVED_FROM_STAGE, onRemoved);
         if(stage != null) onAdded(null);
      }

      public static function shouldShow(gameStage:Stage):Boolean
      {
         if(ALWAYS_SHOW_BUTTONS || DESKTOP_BUTTONS) return true;
         // Em Flash/AIR, prioriza uma capacidade real do ambiente.
         try { if(Multitouch.supportsTouchEvents) return true; }
         catch(error1:Error) {}
         var os:String = Capabilities.os.toLowerCase();
         if(os.indexOf("android") >= 0 || os.indexOf("iphone") >= 0 ||
            os.indexOf("ipad") >= 0 || os.indexOf("ios") >= 0)
            return true;
         // Alguns emuladores exportam JavaScript mas nao um SO mobile.
         try
         {
            if(ExternalInterface.available)
            {
               var supported:* = ExternalInterface.call(
                  "function(){return !!(navigator.maxTouchPoints > 0 || " +
                  "(window.matchMedia && window.matchMedia('(pointer: coarse)').matches));}"
               );
               if(supported === true || supported === "true") return true;
            }
         }
         catch(error2:Error) {}
         return false;
      }

      private function makeButton(picture:Bitmap, action:String):Sprite
      {
         var button:Sprite = new Sprite();
         button.name = action;
         picture.smoothing = true;
         button.addChild(picture);
         button.buttonMode = true;
         button.useHandCursor = true;
         button.mouseChildren = false;
         button.addEventListener(MouseEvent.MOUSE_DOWN, onMouseDown);
         button.addEventListener(MouseEvent.MOUSE_OUT, onMouseOut);
         button.addEventListener(TouchEvent.TOUCH_BEGIN, onTouchBegin);
         button.addEventListener(TouchEvent.TOUCH_OUT, onTouchOut);
         return button;
      }

      private function onAdded(event:Event):void
      {
         if(_disposed || stage == null || _attachedStage == stage) return;
         _attachedStage = stage;
         // Captura global para registrar UP mesmo se soltar sobre outro objeto.
         _attachedStage.addEventListener(MouseEvent.MOUSE_UP, onMouseUp, true, 100);
         _attachedStage.addEventListener(TouchEvent.TOUCH_END, onTouchEnd, true, 100);
         _attachedStage.addEventListener(Event.MOUSE_LEAVE, onLostPointer);
         _attachedStage.addEventListener(Event.DEACTIVATE, onLostPointer);
         _attachedStage.addEventListener(Event.RESIZE, onResize);
         // Em AIR/Flash 10.1+, TOUCH_POINT permite dois dedos independentes.
         try
         {
            if(Multitouch.supportsTouchEvents && Multitouch.inputMode != MultitouchInputMode.TOUCH_POINT)
            {
               _oldTouchMode = Multitouch.inputMode;
               Multitouch.inputMode = MultitouchInputMode.TOUCH_POINT;
               _didSetTouchMode = true;
            }
         }
         catch(error:Error) { _didSetTouchMode = false; }
         positionButtons();
         onStatus(null);
      }

      private function onRemoved(event:Event):void
      {
         cancelInputs();
         if(_attachedStage == null) return;
         _attachedStage.removeEventListener(MouseEvent.MOUSE_UP, onMouseUp, true);
         _attachedStage.removeEventListener(TouchEvent.TOUCH_END, onTouchEnd, true);
         _attachedStage.removeEventListener(Event.MOUSE_LEAVE, onLostPointer);
         _attachedStage.removeEventListener(Event.DEACTIVATE, onLostPointer);
         _attachedStage.removeEventListener(Event.RESIZE, onResize);
         try
         {
            if(_didSetTouchMode && Multitouch.inputMode == MultitouchInputMode.TOUCH_POINT)
               Multitouch.inputMode = _oldTouchMode;
         }
         catch(error:Error) {}
         _didSetTouchMode = false;
         _attachedStage = null;
      }

      private function onResize(event:Event):void { positionButtons(); }

      private function positionButtons():void
      {
         if(_attachedStage == null) return;
         var sw:Number = _attachedStage.stageWidth;
         var sh:Number = _attachedStage.stageHeight;
         if(sw <= 0 || sh <= 0) return;

         // Anchors em areas laterais com base do HUD inferior preservada.
         var angleWidth:Number = Math.min(134, Math.max(82, sw * 0.095));
         var forceWidth:Number = Math.min(176, Math.max(104, sw * 0.12));
         var margin:Number = Math.max(8, Math.min(24, sw * 0.018));
         var bottomSafe:Number = Math.max(58, Math.min(94, sh * 0.11));
         var gap:Number = Math.max(7, Math.min(15, sh * 0.012));

         sizeButton(_up, angleWidth);
         sizeButton(_down, angleWidth);
         sizeButton(_force, forceWidth);
         _down.x = margin;
         _down.y = Math.max(0, sh - bottomSafe - _down.height);
         _up.x = margin;
         _up.y = Math.max(0, _down.y - gap - _up.height);
         var rightHUD:Number = Math.max(36, Math.min(85, sw * 0.06));
         _force.x = Math.max(margin, sw - margin - rightHUD - _force.width);
         _force.y = Math.max(0, sh - bottomSafe - _force.height);
      }

      private function sizeButton(button:Sprite, width:Number):void
      {
         var pic:Bitmap = button.getChildAt(0) as Bitmap;
         if(pic == null || pic.bitmapData == null) return;
         var height:Number = width * pic.bitmapData.height / pic.bitmapData.width;
         pic.width = width;
         pic.height = height;
         button.graphics.clear();
         // Superficie invisivel com area de toque continua (inclui bordas PNG).
         button.graphics.beginFill(0xFFFFFF, 0.001);
         button.graphics.drawRect(0, 0, width, height);
         button.graphics.endFill();
      }

      private function onStatus(event:LivingEvent):void
      {
         var enabled:Boolean = _player != null && _player.shootEnabled;
         alpha = enabled ? 0.93 : 0.30;
         if(!enabled) cancelInputs();
      }

      private function onMouseDown(event:MouseEvent):void
      {
         if(getTimer() < _ignoreMouseUntil || _player == null || !_player.shootEnabled) return;
         _mouseAction = Sprite(event.currentTarget).name;
         updateInputs();
         event.stopPropagation();
      }

      private function onMouseUp(event:MouseEvent):void
      {
         if(getTimer() < _ignoreMouseUntil) return;
         _mouseAction = "";
         updateInputs(); // Liberacao normal = disparo se FORCA estava segurada.
      }

      private function onMouseOut(event:MouseEvent):void
      {
         if(getTimer() < _ignoreMouseUntil) return;
         if(_mouseAction != Sprite(event.currentTarget).name) return;
         var interrupted:Boolean = _mouseAction == "force";
         _mouseAction = "";
         updateInputs();
         if(interrupted && !forceDown) forceCancelSerial++;
      }

      private function onTouchBegin(event:TouchEvent):void
      {
         if(_player == null || !_player.shootEnabled) return;
         _ignoreMouseUntil = getTimer() + 600;
         var interruptedMouseForce:Boolean = _mouseAction == "force";
         _mouseAction = "";
         _touches[String(event.touchPointID)] = Sprite(event.currentTarget).name;
         updateInputs();
         if(interruptedMouseForce && !forceDown) forceCancelSerial++;
         event.stopPropagation();
      }

      private function onTouchEnd(event:TouchEvent):void
      {
         _ignoreMouseUntil = getTimer() + 600;
         var key:String = String(event.touchPointID);
         if(!_touches.hasOwnProperty(key)) return;
         delete _touches[key];
         updateInputs(); // Liberacao voluntaria do dedo: comando de disparo.
      }

      private function onTouchOut(event:TouchEvent):void
      {
         var key:String = String(event.touchPointID);
         if(!_touches.hasOwnProperty(key)) return;
         var interrupted:Boolean = _touches[key] == "force";
         delete _touches[key];
         updateInputs();
         if(interrupted && !forceDown) forceCancelSerial++;
      }

      private function onLostPointer(event:Event):void { cancelInputs(); }

      private function updateInputs():void
      {
         var up:Boolean = _mouseAction == "up";
         var down:Boolean = _mouseAction == "down";
         var fire:Boolean = _mouseAction == "force";
         for each(var action:String in _touches)
         {
            if(action == "up") up = true;
            if(action == "down") down = true;
            if(action == "force") fire = true;
         }
         angleUp = up && !down;
         angleDown = down && !up;
         forceDown = fire;
         _up.alpha = angleUp ? 1 : 0.96;
         _down.alpha = angleDown ? 1 : 0.96;
         _force.alpha = forceDown ? 1 : 0.96;
      }

      private function cancelInputs():void
      {
         var wasCharging:Boolean = forceDown;
         _mouseAction = "";
         _touches = {};
         updateInputs();
         if(wasCharging) forceCancelSerial++;
      }

      public function dispose():void
      {
         if(_disposed) return;
         _disposed = true;
         onRemoved(null);
         removeEventListener(Event.ADDED_TO_STAGE, onAdded);
         removeEventListener(Event.REMOVED_FROM_STAGE, onRemoved);
         if(_player != null)
         {
            _player.removeEventListener(LivingEvent.ATTACKING_CHANGED, onStatus);
            _player.removeEventListener(LivingEvent.DIE, onStatus);
         }
         _up.removeEventListener(MouseEvent.MOUSE_DOWN, onMouseDown);
         _down.removeEventListener(MouseEvent.MOUSE_DOWN, onMouseDown);
         _force.removeEventListener(MouseEvent.MOUSE_DOWN, onMouseDown);
         _up.removeEventListener(MouseEvent.MOUSE_OUT, onMouseOut);
         _down.removeEventListener(MouseEvent.MOUSE_OUT, onMouseOut);
         _force.removeEventListener(MouseEvent.MOUSE_OUT, onMouseOut);
         _up.removeEventListener(TouchEvent.TOUCH_BEGIN, onTouchBegin);
         _down.removeEventListener(TouchEvent.TOUCH_BEGIN, onTouchBegin);
         _force.removeEventListener(TouchEvent.TOUCH_BEGIN, onTouchBegin);
         _up.removeEventListener(TouchEvent.TOUCH_OUT, onTouchOut);
         _down.removeEventListener(TouchEvent.TOUCH_OUT, onTouchOut);
         _force.removeEventListener(TouchEvent.TOUCH_OUT, onTouchOut);
         _player = null;
         if(parent != null) parent.removeChild(this);
      }
   }
}
