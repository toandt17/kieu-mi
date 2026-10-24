import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class Insets {
  static EdgeInsets view(BuildContext context) =>
      MediaQuery.viewPaddingOf(context);

  static double top(BuildContext context) => view(context).top;

  static double bottom(BuildContext context) => view(context).bottom;

  static double tabContentBottom(BuildContext context) => 88;

  static double pageContentBottom(BuildContext context) => bottom(context) + 24;
}

const SystemUiOverlayStyle kLightOverlay = SystemUiOverlayStyle(
  statusBarColor: Colors.transparent,
  statusBarIconBrightness: Brightness.dark,
  statusBarBrightness: Brightness.light,
  systemNavigationBarColor: Colors.transparent,
  systemNavigationBarIconBrightness: Brightness.dark,
  systemNavigationBarContrastEnforced: false,
);

const SystemUiOverlayStyle kDarkOverlay = SystemUiOverlayStyle(
  statusBarColor: Colors.transparent,
  statusBarIconBrightness: Brightness.light,
  statusBarBrightness: Brightness.dark,
  systemNavigationBarColor: Colors.transparent,
  systemNavigationBarIconBrightness: Brightness.dark,
  systemNavigationBarContrastEnforced: false,
);

void enableEdgeToEdge() {
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  SystemChrome.setSystemUIOverlayStyle(kLightOverlay);
}
