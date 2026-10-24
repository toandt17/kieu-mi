import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'screens/auth/login_screen.dart';
import 'screens/main_shell.dart';
import 'services/auth_session.dart';
import 'theme/app_theme.dart';
import 'widgets/safe_insets.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SemanticsBinding.instance.ensureSemantics();
  enableEdgeToEdge();
  await AuthSession.instance.load();
  runApp(const SalesApp());
}

class SalesApp extends StatelessWidget {
  const SalesApp({super.key});

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: kLightOverlay,
      child: MaterialApp(
        title: 'Quản lý bán hàng',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        locale: const Locale('vi', 'VN'),
        supportedLocales: const [Locale('vi', 'VN'), Locale('en')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: AuthSession.instance.isLoggedIn
            ? const MainShell()
            : const LoginScreen(),
      ),
    );
  }
}
