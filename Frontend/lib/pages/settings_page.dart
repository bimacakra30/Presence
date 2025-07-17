import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'login_page.dart';

class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFEAEAEA),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: BackButton(color: Colors.black),
        title: const Text('Settings',
            style: TextStyle(
                color: Colors.black, fontSize: 22, fontWeight: FontWeight.bold)),
        centerTitle: false,
      ),
      body: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
        children: [
          _SectionTitle(icon: Icons.person, title: 'Account'),
          _SectionItem(title: 'Edit Profile', onTap: () {}),
          _SectionItem(title: 'Change Password', onTap: () {}),
          _SectionItem(title: 'Edit Profile', onTap: () {}), // duplikat contoh

          const SizedBox(height: 20),
          _SectionTitle(icon: Icons.notifications, title: 'Notifications'),
          _SectionSwitch(title: 'Notifications'),
          _SectionSwitch(title: 'App Notifications'),

          const SizedBox(height: 20),
          _SectionTitle(icon: Icons.more_horiz, title: 'More'),
          _SectionItem(title: 'Language', onTap: () {}),
          _SectionItem(title: 'About', onTap: () {}),

          const SizedBox(height: 40),
          Center(
            child: ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: Colors.black,
                elevation: 4,
                padding:
                    const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(30)),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Logout'),
              onPressed: () async {
                await FirebaseAuth.instance.signOut();
                if (context.mounted) {
                  Navigator.pushAndRemoveUntil(
                    context,
                    MaterialPageRoute(builder: (context) => const LoginPage()),
                    (route) => false,
                  );
                }
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final IconData icon;
  final String title;

  const _SectionTitle({required this.icon, required this.title});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: Colors.black87),
        const SizedBox(width: 8),
        Text(title,
            style: const TextStyle(
                fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black)),
      ],
    );
  }
}

class _SectionItem extends StatelessWidget {
  final String title;
  final VoidCallback onTap;

  const _SectionItem({required this.title, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(fontSize: 14)),
      onTap: onTap,
    );
  }
}

class _SectionSwitch extends StatefulWidget {
  final String title;

  const _SectionSwitch({required this.title});

  @override
  State<_SectionSwitch> createState() => _SectionSwitchState();
}

class _SectionSwitchState extends State<_SectionSwitch> {
  bool _value = false;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile(
      title: Text(widget.title, style: const TextStyle(fontSize: 14)),
      contentPadding: EdgeInsets.zero,
      value: _value,
      onChanged: (val) => setState(() => _value = val),
    );
  }
}
