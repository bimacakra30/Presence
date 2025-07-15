import 'package:flutter/material.dart';

Route createFadeSlideRoute(Widget page) {
  return PageRouteBuilder(
    transitionDuration: const Duration(milliseconds: 400),
    pageBuilder: (_, __, ___) => page,
    transitionsBuilder: (_, animation, __, child) {
      const beginOffset = Offset(0.0, 0.2); // Slide dari bawah ke atas
      const endOffset = Offset.zero;
      final tween = Tween(begin: beginOffset, end: endOffset);
      final offsetAnimation = animation.drive(tween);

      return FadeTransition(
        opacity: animation,
        child: SlideTransition(
          position: offsetAnimation,
          child: child,
        ),
      );
    },
  );
}
